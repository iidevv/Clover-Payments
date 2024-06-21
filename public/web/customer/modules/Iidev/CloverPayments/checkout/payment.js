function CloverPayments(base) {
  this.callSupermethod("constructor", arguments);

  if (this.base && this.base.length) {
    this.block = new CloverPaymentsView(this.base, this);
  }
}

extend(CloverPayments, AController);

CloverPayments.prototype.name = "CloverPayments";

CloverPayments.prototype.findPattern = ".payment-form-container";

CloverPayments.prototype.blockedByCloverPayments = false;

CloverPayments.prototype.initialize = function (secondary) {
  let key = document.querySelector("#payment-form")?.dataset?.key || "";

  this.base = jQuery(this.findPattern);

  this.cloverInstance = new Clover(key);

  this.form = document.querySelector("form.place");

  var self = this;
  this.hostedPaymentFieldsCreation();

  xcart.microhandlers.add(
    "CloverPaymentsFields",
    ".payment-form-container",
    function () {
      setTimeout(function () {
        self.base = jQuery(".payment-form-container");
        xcart.trigger("checkout.common.anyChange");
        self.hostedPaymentFieldsCreation();
      }, 500);
    }
  );

  if (!secondary) {
    xcart.bind("checkout.common.ready", _.bind(this.handleCheckoutReady, this));
    xcart.bind(
      "checkout.common.anyChange",
      _.bind(this.handleCheckoutAnyChange, this)
    );
    xcart.bind(
      "checkout.shippingAddress.submitted",
      _.bind(this.handleCheckoutAnyChange, this)
    );
    xcart.bind(
      "checkout.billingAddress.submitted",
      _.bind(this.handleCheckoutAnyChange, this)
    );
    xcart.bind(
      "fastlane_section_switched",
      _.bind(this.handleCheckoutAnyChange, this)
    );

    xcart.bind(
      "checkout.paymentTpl.loaded",
      _.bind(this.handlePaymentTplLoaded, this)
    );

    this.handleCheckoutAnyChange();
  }
};

CloverPayments.prototype.createToken = async function () {
  try {
    const result = await this.cloverInstance.createToken();

    return result.token;
  } catch (error) {
    console.log(error);
  }
};

CloverPayments.prototype.setToken = function (token) {
  if (!this.form) return;

  let hiddenInput = document.createElement("input");
  hiddenInput.setAttribute("type", "hidden");
  hiddenInput.setAttribute("name", "source");
  hiddenInput.setAttribute("value", token);
  this.form.querySelector(".form-params").appendChild(hiddenInput);
};

CloverPayments.prototype.handlePaymentTplLoaded = function () {};

CloverPayments.prototype.handleCheckoutAnyChange = function () {
  if (!jQuery(".payment-form-container").length) {
    xcart.trigger("common.unshaded");
    xcart.trigger("checkout.common.unblock");
  }
};

CloverPayments.prototype.validationState = {
  CARD_NUMBER: false,
  CARD_DATE: false,
  CARD_CVV: false,
  CARD_POSTAL_CODE: false,
};

CloverPayments.prototype.isValid = function () {
  const isValid =
    this.validationState.CARD_NUMBER &&
    this.validationState.CARD_DATE &&
    this.validationState.CARD_CVV &&
    this.validationState.CARD_POSTAL_CODE;

  const submitCard = document.querySelector("#save_card");

  if (isValid) {
    xcart.trigger("common.unshaded");
    xcart.trigger("checkout.common.unblock");
    if (submitCard) submitCard.disabled = false;
  } else {
    xcart.trigger("common.shaded");
    xcart.trigger("checkout.common.block");
    if (submitCard) submitCard.disabled = true;
  }

  return isValid;
};

CloverPayments.prototype.validate = function (data) {
  this.validationState["CARD_NUMBER"] =
    !data.CARD_NUMBER.error && data.CARD_NUMBER.touched ? true : false;
  this.validationState["CARD_DATE"] =
    !data.CARD_DATE.error && data.CARD_DATE.touched ? true : false;
  this.validationState["CARD_CVV"] =
    !data.CARD_CVV.error && data.CARD_CVV.touched ? true : false;
  this.validationState["CARD_POSTAL_CODE"] =
    !data.CARD_POSTAL_CODE.error && data.CARD_POSTAL_CODE.touched
      ? true
      : false;

  return this.isValid();
};

CloverPayments.prototype.handleCheckoutReady = async function (event, state) {
};

CloverPayments.prototype.hostedPaymentFieldsCreation = function () {
  if (
    document.querySelector("#card-number iframe") ||
    document.querySelector("#card-date iframe") ||
    document.querySelector("#card-cvv iframe") ||
    document.querySelector("#card-postal-code iframe")
  ) {
    return;
  }

  const elements = this.cloverInstance.elements();
  const styles = {
    body: {
      fontFamily: "Roboto, Open Sans, sans-serif",
      fontSize: "16px",
    },
    input: {
      fontSize: "16px",
      padding: "10px",
      margin: "0px",
      height: "45px",
      borderRadius: "4px",
      backgroundColor: "#fff",
    },

    "input:focus": { outline: "1px solid red" },

    "img.brand": {
      right: "5px",
      top: "7px",
    },
  };

  this.cardNumber = elements.create("CARD_NUMBER", styles);
  this.cardDate = elements.create("CARD_DATE", styles);
  this.cardCvv = elements.create("CARD_CVV", styles);
  this.cardPostalCode = elements.create("CARD_POSTAL_CODE", styles);

  this.cardNumber.mount("#card-number");
  this.cardDate.mount("#card-date");
  this.cardCvv.mount("#card-cvv");
  this.cardPostalCode.mount("#card-postal-code");

  this.hostedPaymentEventListeners();

  this.isValid();
};

CloverPayments.prototype.hostedPaymentEventListeners = function () {
  const cardResponse = document.getElementById("card-response");
  const displayCardNumberError = document.getElementById("card-number-errors");
  const displayCardDateError = document.getElementById("card-date-errors");
  const displayCardCvvError = document.getElementById("card-cvv-errors");
  const displayCardPostalCodeError = document.getElementById(
    "card-postal-code-errors"
  );
  const savedCardSelect = document.getElementById("saved-card");

  if (savedCardSelect) {
    savedCardSelect.addEventListener("change", (e) => {
      const cardData = e.target.value;
      if (!cardData) {
        document.querySelector("#payment-form").style.display = "block";
        xcart.trigger("common.shaded");
        xcart.trigger("checkout.common.block");
      } else {
        document.querySelector("#payment-form").style.display = "none";
        xcart.trigger("common.unshaded");
        xcart.trigger("checkout.common.unblock");
      }
    });
  }

  const isSafeButton = document.querySelector(".save-card-hint");

  if (isSafeButton) {
    isSafeButton.addEventListener("click", (e) => {
      e.preventDefault();
      xcart.trigger("message", {
        type: "info",
        message: "No real credit cards were saved, only special token on the side of the payment processor, that can be used in this store only. The token instructs payment processor to use a credit card but it doesn't contain any of your credit card details.",
      });
    });
  }

  // Handle real-time validation errors from the card element
  this.cardNumber.addEventListener("change", (event) => {
    displayCardNumberError.textContent = "";
    this.validate(event);
  });

  this.cardNumber.addEventListener("blur", (event) => {
    displayCardNumberError.textContent = event.CARD_NUMBER.error;
    this.validate(event);
  });

  this.cardDate.addEventListener("change", (event) => {
    displayCardDateError.textContent = "";
    this.validate(event);
  });

  this.cardDate.addEventListener("blur", (event) => {
    displayCardDateError.textContent = event.CARD_DATE.error;
    this.validate(event);
  });

  this.cardCvv.addEventListener("change", (event) => {
    displayCardCvvError.textContent = "";
    this.validate(event);
  });

  this.cardCvv.addEventListener("blur", (event) => {
    displayCardCvvError.textContent = event.CARD_CVV.error;
    this.validate(event);
  });

  this.cardPostalCode.addEventListener("change", (event) => {
    displayCardPostalCodeError.textContent = "";
    this.validate(event);
  });

  this.cardPostalCode.addEventListener("blur", (event) => {
    displayCardPostalCodeError.textContent = event.CARD_POSTAL_CODE.error;
    this.validate(event);
  });

  this.form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (
      document.getElementById("saved-card") &&
      document.getElementById("saved-card").value
    ) {
      this.form.submit();
      return;
    }

    if (document.querySelector("#save_card")) {
      xcart.trigger("message", {
        type: "info",
        message: "Processing. Please wait",
      });
    }

    const token = await this.createToken();
    if (!token) {
      xcart.trigger("message", {
        type: "info",
        message: "Error. Please try again!",
      });
      location.reload();
      return;
    }
    this.setToken(token);
    this.form.submit();
  });
};

function CloverPaymentsView(base, controller) {
  this.callSupermethod("constructor", arguments);
  this.controller = controller;

  this.bind("local.loaded", _.bind(this.handleLoaded, this));
}

extend(CloverPaymentsView, ALoadable);

CloverPaymentsView.prototype.shadeWidget = false;

// Widget target
CloverPaymentsView.prototype.widgetTarget = "checkout";

// Widget class name
CloverPaymentsView.prototype.widgetClass =
  "\\Iidev\\CloverPayments\\View\\Checkout\\CloverPayments";

CloverPaymentsView.prototype.handleLoaded = function (event, state) {
  this.controller.initialize(true);
};

// Get event namespace (prefix)
CloverPaymentsView.prototype.getEventNamespace = function () {
  return "clover_view";
};

xcart.autoload(CloverPayments);
