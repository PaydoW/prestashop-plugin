# Paydo Payment Gateway for PrestaShop

Accept online payments in PrestaShop via [Paydo](https://paydo.com).

## Requirements

- PrestaShop 8.0 or later
- PHP cURL extension
- PayDo Public Key and Secret Key
- Project ID and JWT Token when separate payment methods are enabled

## Installation

1. Download `paydo.zip` from the [latest release](https://github.com/PaydoW/prestashop-plugin/releases).
2. In PrestaShop, open **Modules → Module Manager → Upload a module**.
3. Upload `paydo.zip` and wait for the installation to finish.
4. Find **Paydo** in the module list and open **Configure**.
5. Enter the Public Key and Secret Key, enable payments, and save the settings.

## Updating from an older version

Do not uninstall the existing module. Upload the new `paydo.zip` over it through **Modules → Module Manager → Upload a module**. PrestaShop will run the included upgrade automatically and preserve the existing PayDo keys and transactions.

Back up the shop files and database before updating.

## Payment options

By default, customers are redirected to the common PayDo Hosted Page.

To show selected PayDo methods directly at checkout:

1. Enable **Internet Acquiring payment methods**.
2. Enter the PayDo **Project ID** and **JWT Token**.
3. Click **Sync from PayDo**.
4. Choose whether to show the common Hosted Page.
5. Select the individual payment methods and save the settings.

The JWT Token is used only to synchronize the available payment methods. Newly synchronized methods are not enabled automatically.

## IPN setup

1. Copy the **IPN URL** shown in the module settings.
2. Sign in to PayDo and open the project IPN settings.
3. Add the copied URL and save it.

Without a configured IPN URL, the shop will not receive automatic payment status updates.

## Support

- [GitHub issues](https://github.com/PaydoW/prestashop-plugin/issues)
- [PayDo API documentation](https://github.com/PaydoW/paydo-api-doc)
- [PayDo support](https://paydo.com/contacts-page-customer-support/)

## License

This project is licensed under the [MIT License](LICENSE).
