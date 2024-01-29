import EmbeddedPlugin from './checkout/embedded-plugin';
import ConfirmFormPlugin from "./checkout/confirm-form-plugin";

const PluginManager = window.PluginManager;
PluginManager.register('EmbeddedPlugin', EmbeddedPlugin, '[data-embedded-plugin]');
PluginManager.register('ConfirmFormPlugin', ConfirmFormPlugin, '[data-confirm-form-plugin]');

// @todo remove self-invoking function
$(function() {
    if($('#dibs-complete-checkout').length !== 0) {
        if($('.flashbags .alert.alert-danger').length !== 0) {
            $('.checkout-main').append('<div class="row"><div class="col-lg-12 col-xl-12 ttop"></div><div class="col-lg-7 col-xl-6 ttmain"></div><div class="col-lg-5 col-xl-6"><div class="row"><div class="col-lg-12 col-xl-12 sstop"></div><div class="col-lg-12 col-xl-12 ssbtm"></div></div></div></div>');
            $('.confirm-main-header, .confirm-product').appendTo('.ttop');
            $('#dibs-checkout-embedded').hide();
            $('.checkout-aside').appendTo('.sstop');
            $('.confirm-address, .confirm-payment-shipping').appendTo('.ttmain');

            $('.is-act-confirmpage .checkout .checkout-main, .is-act-confirmpage .checkout .checkout-aside').css({
                'margin-left' : '0',
                'padding' : '0',
                'flex' : '0 0 100%',
                'max-width' : '100%',
                'width' : '100%'
            });
            $('.checkout').css({'padding' : '70px 50px'});
            $('.ttmain .btn-light').css({'width' : '100%', 'border-color' : '#bcc1c7', 'text-align' : 'center'});
            $('.confirm-billing-address .btn-light').css({'background' : '#fad3d4', 'color' : '#e52427', 'border-color' : '#e52427'});
        }else{
            $('.checkout-main').append('<div class="row"><div class="col-lg-12 col-xl-12 ttop"></div><div class="col-lg-7 col-xl-6 ttmain"></div><div class="col-lg-5 col-xl-6"><div class="row"><div class="col-lg-12 col-xl-12 sstop"></div><div class="col-lg-12 col-xl-12 ssbtm"></div></div></div></div>');
            $('.confirm-main-header, .confirm-product').appendTo('.ttop');
            $('#dibs-checkout-embedded').appendTo('.ttmain');
            $('.checkout-aside').appendTo('.sstop');
            $('.confirm-address, .confirm-payment-shipping').appendTo('.ssbtm');

            $('.col-sm-6.card-col.confirm-billing-address').removeClass('col-sm-6').addClass('col-12 col-sm-6 col-md-6 col-lg-12 col-xl-6');
            $('.col-sm-6.card-col.confirm-shipping-address').removeClass('col-sm-6').addClass('col-12 col-sm-6 col-md-6 col-lg-12 col-xl-6');

            $('.is-act-confirmpage .checkout .checkout-main, .is-act-confirmpage .checkout .checkout-aside').css({
                'margin-left' : '0',
                'padding' : '0',
                'flex' : '0 0 100%',
                'max-width' : '100%',
                'width' : '100%'
            });
            $('.checkout').css({'padding' : '70px 50px'});
            $('.ssbtm .btn-light').css({'width' : '100%', 'border-color' : '#bcc1c7', 'text-align' : 'center'});
        }
    }
});
