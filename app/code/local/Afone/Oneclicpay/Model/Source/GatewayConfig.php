<?php

/**
 * Afone GatewayConfig Action Dropdown source
 */
class Afone_Oneclicpay_Model_Source_GatewayConfig
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => "HOMOLOGATION",
                'label' => Mage::helper('oneclicpay')->__('Homologation')
            ),
            array(
                'value' => "PRODUCTION",
                'label' => Mage::helper('oneclicpay')->__('Production')
            ),
        );
    }
}
