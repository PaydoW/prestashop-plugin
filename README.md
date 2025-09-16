# Paydo Payment Gateway for PrestaShop

Accept online payments in **PrestaShop** via [Paydo.com](https://paydo.com).

## Requirements
- PrestaShop **8.0+**

---

## Installation and Setup (via PrestaShop Admin Panel)

### 1) Upload the module
1. Go to the [latest release](https://github.com/PaydoW/prestashop-plugin/releases).
2. Download the latest **`paydo.zip`** file.
3. In your PrestaShop admin panel go to **Modules → Module Manager → Upload a module**.
4. Drag & drop the `paydo.zip` file or select it manually.
5. Wait until the module is successfully installed.

### 2) Enable the module
1. After installation, locate **Paydo Payment Gateway** in the **Modules list**.
2. Click **Enable** to activate the module.

### 3) Configure the payment method
1. Go to **Modules → Module Manager**.
2. Find **Paydo Payment Gateway** and click **Configure**.
3. In the settings form, fill in:
   - **Public Key** — your Paydo project Public Key.
   - **Secret Key** — your Paydo project Secret Key.
   - **IPN URL** — automatically generated link. Copy this link into your Paydo dashboard.
   - Set **Status → Enabled**.

---

## How to get Public/Secret Keys and configure IPN in Paydo

### Public/Secret Keys
1. Log in to your account on **Paydo.com**.
2. Go to **Overview → Project (website) details**.
3. Open the **General information** tab.
4. Copy your **Public Key** and **Secret Key** and paste them into the module settings in PrestaShop.

### IPN (payment notifications)
1. In your Paydo dashboard go to **IPN settings**.
2. Click **Add new IPN**.
3. Paste the **IPN URL** from the module settings in PrestaShop.
4. Save.

> **Note:** Without correct IPN setup your PrestaShop store will not automatically receive payment status updates.

---

## Support

* [Open an issue](https://github.com/PaydoW/prestashop-plugin/issues) if you are having issues with this module.
* [PayDo Documentation](https://github.com/PaydoW/paydo-api-doc)
* [Contact PayDo support](https://paydo.com/contacts-page-customer-support/)

**TIP**: When contacting support it will help us if you provide:

* PrestaShop Version
* PHP Version and MySQL Version
* Theme in use
* Other modules you have installed (some may conflict)
* Configuration settings for the module (screenshots recommended)
* Any log files that will help (e.g., web server error logs)
* Screenshots of error messages if applicable

---

## Contribute

Would you like to help with this project? Great! You don’t have to be a developer, either.
If you’ve found a bug or have an idea for an improvement, please open an [issue](https://github.com/PaydoW/prestashop-plugin/issues) and tell us about it.

If you *are* a developer wanting to contribute an enhancement, bugfix or other patch to this project,
please fork this repository and submit a pull request detailing your changes. We review all PRs!

This open-source project is released under the [MIT license](http://opensource.org/licenses/MIT),
which means you are free to use this project’s code in your own project.

---

## License

Please refer to the [LICENSE](https://github.com/PaydoW/prestashop-plugin/blob/master/LICENSE) file that came with this project.
