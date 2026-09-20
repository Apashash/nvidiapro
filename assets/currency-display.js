(function () {
    'use strict';

    const STORAGE_KEY = 'gd_currency';
    const configuredRate = Number(window.GD_USDT_FCFA_RATE);
    const usdtRate = Number.isFinite(configuredRate) && configuredRate > 0 ? configuredRate : 600;

    function getCurrency() {
        return window.localStorage.getItem(STORAGE_KEY) === 'USDT' ? 'USDT' : 'FCFA';
    }

    function formatAmount(amount, currency) {
        const value = Number(amount) || 0;
        if (currency === 'USDT') {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 5,
                maximumFractionDigits: 5,
            }).format(value / usdtRate);
        }
        return new Intl.NumberFormat('fr-FR', {
            maximumFractionDigits: 2,
        }).format(value);
    }

    function render() {
        const currency = getCurrency();
        document.querySelectorAll('[data-currency-amount]').forEach((element) => {
            element.textContent = formatAmount(element.dataset.currencyAmount, currency);
        });
        document.querySelectorAll('[data-currency-code]').forEach((element) => {
            element.textContent = currency;
        });
        document.querySelectorAll('[data-currency-selector]').forEach((selector) => {
            selector.value = currency;
        });
    }

    function setCurrency(currency) {
        window.localStorage.setItem(STORAGE_KEY, currency === 'USDT' ? 'USDT' : 'FCFA');
        render();
    }

    window.GDCurrency = {
        formatAmount,
        getCurrency,
        setCurrency,
        rate: usdtRate,
    };

    document.addEventListener('DOMContentLoaded', render);
})();