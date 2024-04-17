<?php

namespace Iidev\CloverPayments\Command;

use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use Iidev\CloverPayments\Model\Payment\Processor\CloverPayments;
use Qualiteam\SkinActXPaymentsSubscriptions\Core\Converter as Converter;
use Qualiteam\SkinActXPaymentsSubscriptions\Model\Base\ASubscriptionPlan;
use Qualiteam\SkinActXPaymentsSubscriptions\Model\Repo\Subscription as RepoSubscription;
use Qualiteam\SkinActXPaymentsSubscriptions\Model\Subscription as Subscription;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XLite\Core\CommonCell;
use XLite\Core\Config;
use XLite\Core\Database;
use XLite\Core\Request;
use XLite\Model\Base\Surcharge;
use XLite\Model\Order;
use XLite\Model\Order\Status\Payment;
use XLite\Model\Order\Status\Shipping;
use XLite\Model\OrderItem;
use XLite\Model\Payment\Transaction;
use XLite\Model\Shipping\Method;

class CloverPaymentsCronCommand extends Command
{
    protected static $defaultName = 'CloverPayments:CloverPaymentsCronCommand';

    const STATUS_ACTIVE = true;
    const STATUS_HALT = false;

    /**
     * Time limit (seconds)
     *
     * @var integer
     */
    protected $timeLimit = 600;

    /**
     * Memory limit (bytes)
     *
     * @var integer
     */
    protected $memoryLimit = 4000000;

    /**
     * Memory limit from memory_limit PHP setting (bytes)
     *
     * @var integer
     */
    protected $memoryLimitIni;

    /**
     * Start time
     *
     * @var integer
     */
    protected $startTime;

    /**
     * Start memory
     *
     * @var integer
     */
    protected $startMemory;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger;
    }

    /**
     * isPaymentShippingSectionVisible
     *
     * @return boolean
     */
    public function isPaymentShippingSectionVisible()
    {
        return true;
    }

    /**
     * isPaymentSectionVisible
     *
     * @return boolean
     */
    public function isPaymentSectionVisible()
    {
        return true;
    }

    /**
     * isShippingSectionVisible
     *
     * @return boolean
     */
    public function isShippingSectionVisible()
    {
        return true;
    }

    protected function configure()
    {
        $this->addOption('fakeTime', 'fakeTime', InputArgument::OPTIONAL, 'Set fake date in dd.mm.yyyy format');
    }

    /**
     * Preprocessor for no-action
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fakeTimeOpt = $input->getOption('fakeTime');

        if (preg_match('/^((\d{2})\.(\d{2})\.(\d{4}))$/', $fakeTimeOpt, $m)) {
            $fakeTime = mktime(0, 0, 0, $m[3], $m[2], $m[4]);
            define('XPS_START_TIME', $fakeTime);
            unset($fakeTime);
        }

        $this->setThreadResourceCheckpoint();

        $status = static::STATUS_ACTIVE;

        $this->writeLog('Task started: ' . Converter::getDate('Y-m-d H:i:s'));

        foreach ($this->getActiveSubscriptions() as $subscription) {

            $this->writeLogSubscription($subscription, 'Active subscription START.');

            $order = $this->getOrder($subscription);

            if ($order && $subscription->getStatus() !== ASubscriptionPlan::STATUS_FAILED) {
                $this->processOrder($order, $subscription);
            }

            if (!$this->checkThreadResource()) {
                $status = static::STATUS_HALT;

                break;
            }

            $this->writeLogSubscription($subscription, 'Active subscription END.');
        }

        if (static::STATUS_ACTIVE == $status) {
            foreach ($this->getPendingSubscriptions() as $subscription) {

                $this->writeLogSubscription($subscription, 'Pending subscription START.');

                $order = $this->getValidLastOrder($subscription);

                // only for new order
                if (!$order) {
                    $order = $this->getOrder($subscription);
                    if ($order) {
                        $this->sendOrderNotification($order);
                    }
                }

                if (!$this->checkThreadResource()) {
                    $status = static::STATUS_HALT;

                    break;
                }

                $this->writeLogSubscription($subscription, 'Pending subscription END.');
            }
        }

        if (static::STATUS_HALT == $status) {

            $this->printThreadResource($output);

        } else {

            $this->saveLastCompletedTime();
        }

        return Command::SUCCESS;
    }

    /**
     * Get active subscriptions
     *
     * @return array
     */
    protected function getActiveSubscriptions()
    {
        $cnd = new CommonCell();

        $cnd->{RepoSubscription::SEARCH_PAY_TODAY} = null;

        return $this->getRepo()->search($cnd);
    }

    /**
     * Get order
     *
     * @param Subscription $subscription Subscription
     *
     * @return Order
     */
    protected function getOrder($subscription)
    {
        $order = $this->getValidLastOrder($subscription);

        if ($order) {
            $order->setPaymentMethod($this->getSavedCardMethod());
            $order->setPaymentStatus(Payment::STATUS_QUEUED);
            $order->update();
            Database::getEM()->flush();

            $this->writeLogOrder($order, 'Get valid last order');

        } else {
            $order = $this->createOrder($subscription);

            $this->writeLogOrder($order, 'Create order');
        }

        return $order;
    }

    /**
     * getValidLastOrder
     *
     * @param Subscription $subscription Subscription
     *
     * @return Order
     */
    protected function getValidLastOrder($subscription)
    {
        $orderId = $subscription->getLastOrderId();

        $order = $orderId
            ? Database::getRepo(Order::class)->find($orderId)
            : false;

        return $order
            && (
                $order->getFirstOpenPaymentTransaction()
                || Order::ORDER_ZERO >= $order->getTotal()
            )
            && (
                $order->getPaymentStatusCode() == Payment::STATUS_QUEUED
                || 0 < $subscription->getFailedTries()
            )
            ? $order
            : null;
    }

    /**
     * Create order based on subscription
     *
     * @param Subscription $subscription Subscription
     *
     * @return Order
     */
    protected function createOrder($subscription)
    {
        $orderItem = $this->createOrderItem($subscription);

        if ($orderItem) {
            $order = $this->createOrderObject($subscription);

            if ($order) {
                $order->addItem($orderItem);

                if ($subscription->getCalculateShipping()) {
                    $shippingId = $subscription->getShippingId();
                    $profile = $order->getProfile();
                    $orderShippingAddress = $order->getShippingAddress();
                    $subscriptionShippingAddress = $subscription->getShippingAddress();

                    if (!$orderShippingAddress->equals($subscriptionShippingAddress)) {
                        $extraAddress = $subscriptionShippingAddress->cloneEntity();
                        $extraAddress->setProfile($profile);
                        $extraAddress->setIsShipping(true);
                        $orderShippingAddress->setIsShipping(false);
                        Database::getEM()->persist($extraAddress);
                        Database::getEM()->flush();
                        $profile->setShippingAddress($extraAddress);
                    }

                    $shippingMethod = Database::getRepo(Method::class)->findOneBy(
                        [
                            'method_id' => $shippingId,
                            'enabled' => true,
                        ]
                    );

                    $shippingRates = $order->getModifier(Surcharge::TYPE_SHIPPING, 'SHIPPING')->getRates();

                    $shippingIdsArray = [];
                    foreach ($shippingRates as $rate) {
                        $shippingIdsArray[] = $rate->getMethodId();
                    }

                    if (!is_null($shippingMethod) && in_array($shippingMethod->getMethodId(), $shippingIdsArray)) {
                        $order->renewShippingMethod();
                        $order->setShippingMethodName($shippingMethod->getName());
                        $order->setShippingId($shippingMethod->getMethodId());
                    } else {
                        $this->writeLog('Shipping method #' . $shippingId . ' for subscription #' . $subscription->getId()
                            . ' is not available, select other shipping address');
                        $subscription->stopSubscription($order);
                        $subscription->update();
                    }

                }

                $order->calculate();
                $order->setPaymentMethod($this->getSavedCardMethod());
                $order->setPaymentStatus(Payment::STATUS_QUEUED);
                $order->setShippingStatus(Shipping::STATUS_NEW);
                $order->markAsOrder();
                $order->update();

                $subscription->setLastOrder($order);
                $subscription->update();
            }

        } else {
            $order = null;
        }

        return $order;
    }

    /**
     * Create order object
     *
     * @param Subscription $subscription Subscription
     *
     * @return Order
     */
    protected function createOrderObject($subscription)
    {
        $initialOrder = $subscription->getInitialOrder();

        if ($initialOrder) {
            $order = new Order();
            $order->setCurrency($initialOrder->getCurrency());
            $order->setOrderNumber(Database::getRepo(Order::class)->findNextOrderNumber());

            if ($initialOrder->getOrigProfile()) {
                $order->setProfileCopy($initialOrder->getOrigProfile());
            } else {
                $clonedProfile = $initialOrder->getProfile()->cloneEntity();
                $order->setProfile($clonedProfile);
                $clonedProfile->setOrder($order);
            }

            $order->create();

        } else {
            $order = null;
        }

        return $order;
    }


    /**
     * create OrderItem
     * todo: rewrite to work with deleted product
     *
     * @param Subscription $subscription Subscription
     *
     * @return OrderItem
     */
    protected function createOrderItem($subscription)
    {
        $orderItem = null;

        $product = $subscription->getProduct();

        if ($product) {

            $orderItem = $subscription->getInitialOrderItem()->cloneEntity();

            if ($orderItem) {
                $orderItem->setProduct($product);
                $orderItem->setSubscription($subscription);
                $orderItem->setPrice($subscription->getFee());
                $orderItem->setItemNetPrice($subscription->getFee());
                $orderItem->calculate();
                $orderItem->create();
            }
        }

        return $orderItem;
    }

    /**
     * Process order
     *
     * @param Order $order Order
     * @param Subscription $subscription Subscription
     *
     * @return void
     */
    protected function processOrder(Order $order, Subscription $subscription)
    {
        $transaction = $order->getFirstOpenPaymentTransaction();

        if ($transaction) {

            // Add saved card to request
            $card = $subscription->getXpcData();
            if ($card) {
                Request::getInstance()->saved_card_select = $card->getTransaction();
            }

            // Execute payment
            $transaction->handleCheckoutAction();

            Database::getEM()->refresh($order);
            Database::getEM()->refresh($transaction);

            if ($order->isPayed()) {
                $paymentStatus = $order->getCalculatedPaymentStatus(true);
                if ($paymentStatus == Payment::STATUS_QUEUED) {
                    $paymentStatus = Payment::STATUS_PAID;
                }
                $order->setPaymentStatus($paymentStatus);
                $order->processSucceed();

            } elseif ($transaction->isFailed()) {
                $paymentStatus = Payment::STATUS_DECLINED;
                $order->setPaymentStatus($paymentStatus);
                Database::getEM()->flush();

            } else {
                $paymentStatus = Payment::STATUS_QUEUED;
                $order->setPaymentStatus($paymentStatus);
                $order->processSucceed();
            }

            // Update subscription by payment results
            $order->getSubscription()->updateByPaymentTransaction($transaction);

            $this->writeLogOrder($order, 'Processed order');

        } elseif (Order::ORDER_ZERO >= $order->getTotal()) {
            $paymentStatus = Payment::STATUS_PAID;
            $order->setPaymentStatus($paymentStatus);
            $order->processSucceed();
            $fakeTransaction = new Transaction();
            $fakeTransaction->setOrder($order);
            $fakeTransaction->setStatus(Transaction::STATUS_SUCCESS);
            $order->getSubscription()->updateByPaymentTransaction($fakeTransaction);

        } else {

            $this->writeLog('Failed to find first open transaction for order!');
        }
    }

    /**
     * Send order notification
     *
     * @param Order $order Order
     *
     * @return string
     */
    protected function sendOrderNotification($order)
    {
        $paymentStatus = Payment::STATUS_QUEUED;
        $order->setPaymentStatus($paymentStatus);
        $order->setShippingStatus(Shipping::STATUS_NEW);

        $order->processSucceed();

        return $paymentStatus;
    }

    /**
     * Get pending subscriptions
     *
     * @return array
     */
    protected function getPendingSubscriptions()
    {
        $notificationDays = Config::getInstance()->Qualiteam->SkinActXPaymentsSubscriptions->notification_days;
        $realDate = Converter::convertTimeToDayStart(Converter::now()) + $notificationDays * ASubscriptionPlan::DAY_IN_SECONDS;

        $cnd = new CommonCell();

        $cnd->{RepoSubscription::SEARCH_STATUS} = RepoSubscription::STATUS_ACTIVE_OR_RESTARTED;
        $cnd->{RepoSubscription::SEARCH_REAL_DATE} = $realDate;

        return $this->getRepo()->search($cnd);
    }

    /**
     * Get repo
     *
     * @return EntityRepository
     */
    protected function getRepo()
    {
        return Database::getRepo(Subscription::class);
    }

    /**
     * Get saved card method
     *
     * @return \XLite\Model\Payment\Method
     */
    protected function getSavedCardMethod()
    {
        $class = CloverPayments::class;

        return Database::getRepo(\XLite\Model\Payment\Method::class)->findOneBy(
            [
                'class' => $class,
            ]
        );
    }

    /**
     * Thread resource checkpoint
     *
     * @return void
     */
    protected function setThreadResourceCheckpoint()
    {
        $this->startTime = time();
        $this->startMemory = memory_get_usage(true);
        $this->memoryLimitIni = \XLite\Core\Converter::convertShortSize(ini_get('memory_limit') ?: '16M');
    }

    /**
     * Check thread resource
     *
     * @return boolean
     */
    protected function checkThreadResource()
    {
        return time() - $this->startTime < $this->timeLimit
            && $this->memoryLimitIni - memory_get_usage(true) > $this->memoryLimit;
    }

    /**
     * Print thread resource
     *
     * @return void
     */
    protected function printThreadResource(OutputInterface $output)
    {
        $time = gmdate('H:i:s', \XLite\Core\Converter::time() - $this->startTime);
        $memory = \XLite\Core\Converter::formatFileSize(memory_get_usage(true));
        $output->writeln('Step is interrupted (time: ' . $time . '; memory usage: ' . $memory . ')');
    }

    /**
     * Write log to the output and to the file
     *
     * @param string $str Log string
     *
     * @return void
     */
    protected function writeLog($str)
    {
        $this->logger->info($str);

        echo $str . PHP_EOL;
    }

    /**
     * Write log to the output or to the file
     *
     * @return void
     */
    protected function writeLogOrder($order, $message)
    {
        if ($order) {

            $message .= ' Order #' . $order->getOrderNumber()
                . ' (id: ' . $order->getOrderId() . ')'
                . ' Total: ' . $order->getTotal()
                . ' Status: ' . $order->getPaymentStatus()->getCode();

        } else {

            $message .= ' unknown order';
        }

        $this->writeLog($message);
    }

    /**
     * Write log to the output or to the file
     *
     * @return void
     */
    protected function writeLogSubscription($subscription, $message)
    {
        if ($subscription) {

            $message .= ' Subscription #' . $subscription->getId()
                . ', Successful payments: ' . $subscription->getSuccessTries()
                . ', Failed attempts: ' . $subscription->getFailedTries()
                . ', Real date: ' . Converter::getDate('Y-m-d H:i:s', $subscription->getRealDate());
        }

        $this->writeLog($message);
    }

    /**
     * Save last completed cron time
     *
     * @return void
     */
    protected function saveLastCompletedTime()
    {
        $setting = Database::getRepo('XLite\Model\Config')->findOneBy(
            [
                'name' => 'cron_last_time_completed',
                'category' => 'Qualiteam\SkinActXPaymentsSubscriptions'
            ]
        );

        Database::getRepo('XLite\Model\Config')->update(
            $setting,
            ['value' => Converter::now()]
        );

        Config::updateInstance();
    }
}
