function CloverPayments(base) {
  this.callSupermethod("constructor", arguments);

  if (this.base && this.base.length) {
    this.block = new CloverPaymentsView(this.base, this);
  }
}

extend(CloverPayments, AController);

CloverPayments.prototype.name = "CloverPayments";

CloverPayments.prototype.findPattern = ".payment-form-container";

CloverPayments.prototype.isSavedCardSelected = false;

CloverPayments.prototype.validationState = {
  CARD_NUMBER: false,
  CARD_DATE: false,
  CARD_CVV: false,
  CARD_POSTAL_CODE: false,
};

CloverPayments.prototype.initialize = function (secondary = false) {
  let key = document.querySelector("#payment-form")?.dataset?.key || "";

  this.base = document.querySelector(this.findPattern);

  this.cloverInstance = new Clover(key);

  this.form = document.querySelector("form.place");
  
  this.disableCheckout();
  this.hostedPaymentFieldsCreation();
  this.hostedPaymentEventListeners();

  const self = this;

  xcart.microhandlers.add(
    "CloverPaymentsFields",
    ".payment-form-container",
    function () {
      setTimeout(function () {
        self.disableCheckout();
        self.hostedPaymentFieldsCreation();
        self.hostedPaymentEventListeners();

        xcart.trigger("checkout.common.anyChange");
      }, 500);
    }
  );

  if (!secondary) {
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

    this.handleCheckoutAnyChange();
  }
};

CloverPayments.prototype.createToken = async function () {
  try {
    const result = await this.cloverInstance.createToken();

    return result.token;
  } catch (error) {
    console.error("Token creation failed:", error);
    
    return null;
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

CloverPayments.prototype.handleCheckoutAnyChange = function () {
  if (!document.querySelector(this.findPattern)) {
    this.enableCheckout();
  }
};

CloverPayments.prototype.debounce = function (func, wait) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
};

CloverPayments.prototype.isValid = function () {
  if (this.isSavedCardSelected) {
    return true;
  }

  const isValid =
    this.validationState.CARD_NUMBER &&
    this.validationState.CARD_DATE &&
    this.validationState.CARD_CVV &&
    this.validationState.CARD_POSTAL_CODE;

  const submitCard = document.querySelector("#save_card");

  if (isValid) {
    this.enableCheckout();
    if (submitCard) submitCard.disabled = false;
  } else {
    this.disableCheckout();
    if (submitCard) submitCard.disabled = true;
  }

  return isValid;
};

CloverPayments.prototype.validate = function (data) {
  Object.keys(this.validationState).forEach((key) => {
    this.validationState[key] = !data[key].error && data[key].touched;
  });

  return this.isValid();
};

CloverPayments.prototype.enableCheckout = function () {
  xcart.trigger("common.unshaded");
  xcart.trigger("checkout.common.unblock");
};

CloverPayments.prototype.disableCheckout = function () {
  xcart.trigger("common.shaded");
  xcart.trigger("checkout.common.block");
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
};

CloverPayments.prototype.handleFormSubmit = async function (e) {
  e.preventDefault();

  this.disableCheckout();

  if (!document.querySelector(this.findPattern)) {
    this.form.submit();
    return;
  }

  if (
    document.getElementById("saved-card") &&
    document.getElementById("saved-card").value
  ) {
    this.form.submit();
    return;
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
};

CloverPayments.prototype.hostedPaymentEventListeners = function () {
  const events = {
    CARD_NUMBER: this.cardNumber,
    CARD_DATE: this.cardDate,
    CARD_CVV: this.cardCvv,
    CARD_POSTAL_CODE: this.cardPostalCode,
  };

  const displayErrors = {
    CARD_NUMBER: document.getElementById("card-number-errors"),
    CARD_DATE: document.getElementById("card-date-errors"),
    CARD_CVV: document.getElementById("card-cvv-errors"),
    CARD_POSTAL_CODE: document.getElementById("card-postal-code-errors"),
  };

  const onEventChange = this.debounce((key, event) => {
    displayErrors[key].textContent = "";
    this.validate(event);
  }, 500);

  const onEventBlur = this.debounce((key, event) => {
    displayErrors[key].textContent = event[key]?.error || "";
    this.validate(event);
  }, 500);

  Object.keys(events).forEach((key) => {
    events[key].addEventListener("change", onEventChange.bind(this, key));
    events[key].addEventListener("blur", onEventBlur.bind(this, key));
  });

  if (document.getElementById("saved-card")) {
    document.getElementById("saved-card").addEventListener("change", (e) => {
      const cardData = e.target.value;
      if (cardData) {
        document.querySelector("#payment-form").style.display = "none";

        this.enableCheckout();

        this.isSavedCardSelected = true;
      } else {
        document.querySelector("#payment-form").style.display = "block";

        this.disableCheckout();

        this.isSavedCardSelected = false;
      }
    });
  }

  if (document.querySelector(".save-card-hint")) {
    document.querySelector(".save-card-hint").addEventListener("click", (e) => {
      e.preventDefault();
      xcart.trigger("message", {
        type: "info",
        message:
          "No real credit cards were saved, only special token on the side of the payment processor, that can be used in this store only. The token instructs payment processor to use a credit card but it doesn't contain any of your credit card details.",
      });
    });
  }

  if (this._boundHandleFormSubmit) {
    this.form.removeEventListener("submit", this._boundHandleFormSubmit);
  }

  this._boundHandleFormSubmit = this.handleFormSubmit.bind(this);
  this.form.addEventListener("submit", this._boundHandleFormSubmit);
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
