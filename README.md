# Montonio Payment Gateway Compatability with Hyvä Checkout

## Installation

1. Install [Montonio_Hirepurchase](https://www.montonio.com/integrations/magento).
If you cannot find the latest version linked in their documentation then use this one:
https://public.montonio.com/plugins/magento/2/Montonio_Hirepurchase_2.1.3_Ut8Vat.zip.
The only reliable way to get the latest version of Magento2 package is to ask Montonio support (chat on partner.montonio.com).
2. Install Compatability Module by running
```bash
composer require magebitcom/magento2-hyva-checkout-montonio-hirepurchase
```
3. Enable Module
```bash
bin/magento module:enable Magebit_HyvaMontonioHirepurchase && bin/magento setup:upgrade
```

---
![magebit (1)](https://github.com/user-attachments/assets/cdc904ce-e839-40a0-a86f-792f7ab7961f)

*Developed by Magebit. Have questions or need help? Contact us at info@magebit.com or on our [website](https://magebit.com/contact).*
