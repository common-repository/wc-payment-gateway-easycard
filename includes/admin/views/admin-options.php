<h3><?php _e( 'EasyCard', 'wc-payment-gateway-easycard' ); ?></h3>

<div class="gateway-banner updated">
  <img src="<?php echo esc_attr__(WC_EasyCard()->plugin_url() . '/assets/images/logo.png'); ?>" />
  <p class="main"><strong><?php echo esc_attr__( 'Getting started', 'wc-payment-gateway-easycard' ); ?></strong></p>
  <p><?php echo esc_attr__( 'EasyCard is Israel\'s leading payment gateway for SMB merchants.', 'wc-payment-gateway-easycard' ); ?></p>

  <?php if( empty( $this->public_key ) ) { ?>
  <p><a href="https://merchant.e-c.co.il" target="_blank" class="button button-primary"><?php echo esc_attr__( 'Sign up for EasyCard', 'wc-payment-gateway-easycard' ); ?></a> <a href="https://ecng-transactions.azurewebsites.net/api-docs/index.html" target="_blank" class="button"><?php echo esc_attr__( 'Documentation', 'wc-payment-gateway-easycard' ); ?></a></p>
  <?php } ?>
</div>

<table class="form-table">
  <?php $this->generate_settings_html(); ?>
  <script type="text/javascript">
  jQuery( '#woocommerce_easycard_sandbox' ).change( function () {
    var sandbox = jQuery( '#woocommerce_easycard_sandbox_public_key, #woocommerce_easycard_sandbox_private_key' ).closest( 'tr' ),
    production  = jQuery( '#woocommerce_easycard_public_key, #woocommerce_easycard_private_key' ).closest( 'tr' );

    if ( jQuery( this ).is( ':checked' ) ) {
      sandbox.show();
      production.hide();
    } else {
      sandbox.hide();
      production.show();
    }
  }).change();
  </script>
</table>
