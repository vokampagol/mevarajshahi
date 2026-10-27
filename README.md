# Sapahar Xpress

Static POS and inventory management web app for Sapahar Xpress.

## Features
- Home page with email/password and Google sign-in
- Password reset by email
- Dashboard overview
- POS / sales flow
- Shared Firebase inventory and sales data
- Outlet listing
- Buyer-shop directory with Gemini visiting-card extraction
- Buyer sales with product-category quantity and amount reports
- Invoice modal

## Firebase setup
The app uses Firebase Authentication, Cloud Firestore Standard, and Firebase Storage. GitHub Pages can only host the static `.html` files; PHP endpoints must run on cPanel.

1. Create a Firebase project and add a Web app in Firebase Console.
2. Under Authentication > Sign-in method, enable **Email/Password** and **Google**.
3. Under Authentication > Settings > Authorized domains, add your GitHub Pages domain.
4. Create a Cloud Firestore database.
5. Keep `firebase-config.js` set to the Web app config for the `sapahar-xpress` project. The Firebase Web config is intended for browser use; access to app data is enforced by `firestore.rules`.
6. Create the admin account using Google sign-in with `sapaharitsolution@gmail.com`, or add that email under Authentication > Users. Verify the email if using email/password.
7. Enable Firebase Storage, then publish `firestore.rules` in Firestore > Rules and `storage.rules` in Storage > Rules.
8. Sign in with the verified admin account. On first sign-in, the app initializes products and outlets from its existing browser data (or the built-in catalog). Buyer shops are kept separately from company outlets.

The configured admin email is `sapaharitsolution@gmail.com`. For safety, the current prototype limits Firestore and Storage access—including POS sales—to that verified admin. Add staff only after setting an explicit staff allowlist and reviewing the data access rules. Do not replace the rules with public read/write rules.

Switching to a new Firebase project does not copy existing Authentication users, Firestore data, or Storage files. Migrate those separately if they need to be retained.

Accounts are managed in Firebase Authentication; this static app does not provide public account registration. Password reset is available from the sign-in page. Google sign-in is the recommended way for the admin to use that Gmail account.

## Run locally
Serve the folder locally (Firebase Auth does not work reliably from a `file://` URL) with:

```bash
python -m http.server 8000
```

Then visit `http://localhost:8000/index.html` and add `localhost` to Firebase's authorized domains. File uploads and Gemini card scanning require a PHP-capable server and are not available from GitHub Pages or Python's static server.

## cPanel PHP deployment
The PHP pages wrap the existing HTML templates so the static Pages copy can remain available. The `.cpanel.yml` deployment task copies only runtime files, including the otherwise easily missed hidden `.htaccess`. It expects the cPanel deployment path `/home/sapahare/store.sapaharexpress.com`; change `DEPLOYPATH` there if your cPanel account or target directory differs. Alternatively, copy the following runtime files and folders into the cPanel domain's document root (usually `public_html`):

- `index.php`, `mobile.php`, `index.html`, and `mobile.html`
- `style.css`, `firebase-config.js`, `.htaccess`, and `api/`

Then:

1. In cPanel, select PHP 8.1+ for the domain and enable the `curl` and `fileinfo` PHP extensions.
2. Make sure Apache `mod_rewrite` and `.htaccess` overrides are enabled. `index.php` is the cPanel entry point; the PHP wrappers include the HTML templates beside them.
3. Confirm `api/config.php` contains the same Storage bucket as `firebase-config.js`.
4. In Firebase Console, enable Storage. In Authentication > Settings > Authorized domains, add the cPanel hostname.
5. Publish the repository's `firestore.rules` in Firestore > Rules and `storage.rules` in Storage > Rules. Or, after reviewing the rules, deploy Firestore rules with `npx -y firebase-tools@latest deploy --only firestore:rules --project sapahar-xpress`. These rule files can stay in the repository and do not need to be uploaded to the public document root.
6. In cPanel File Manager, create `api/secrets.local.php` (the deployed app's `api/.htaccess` blocks direct web access to this file). Add the server-only PHP configuration below, replacing both values with your Firebase Web API key and a **newly rotated Gemini key**. This file is excluded from Git and is not overwritten by deployment. Alternatively, set the same values as server environment variables named `GEMINI_API_KEY` and `FIREBASE_WEB_API_KEY`. Never put the Gemini key in `firebase-config.js`, HTML, JavaScript, or a tracked file.

   ```php
   <?php
   declare(strict_types=1);

   return [
       'gemini_api_key' => 'NEW_GEMINI_KEY',
       'firebase_web_api_key' => 'FIREBASE_WEB_API_KEY',
   ];
   ```
7. Ensure PHP `upload_max_filesize` is at least `10M` and `post_max_size` is at least `12M`. The upload and Gemini endpoints validate file MIME and size. The Gemini endpoint verifies the Firebase ID token and permits only the verified admin; the Gemini key stays on the server.
8. Deploy the latest Git commit from cPanel's **Pull or Deploy** tab. If the deployment remains queued, refresh the cPanel page once and check the deployment information; do not repeatedly start deployments. Confirm that `mobile.html`, `api/gemini-shop-extract.php`, `api/config.php`, and `api/.htaccess` have been updated in the document root.
9. Open the cPanel-hosted URL, for example `https://store.sapaharexpress.com/mobile.php`. In **Buyer Shops**, scan a visiting card, verify/edit the extracted fields and save the shop. In POS, select that buyer before completing a sale. Buyer reports show the quantity and amount sold by product category; older sales without a buyer-shop link are not retroactively assigned.

If scanning fails, the endpoint reports the Gemini HTTP status without exposing the API key. HTTP 400 usually means Gemini rejected the key or request; HTTP 403 usually means the key is invalid/restricted or the Generative Language API is unavailable to the project; HTTP 429 means quota or rate limiting. Check that `api/secrets.local.php` on the live site contains real values (not the `PASTE_...` placeholders), then check cPanel **Metrics > Errors** for the matching server-side status. Do not send API keys or secret-file screenshots.

`api/upload.php` sends uploaded shop/product media directly to Firebase Storage using the authenticated Firebase token; Storage Rules are the authorization gate. `api/gemini-shop-extract.php` verifies the Firebase ID token with Firebase Authentication, permits only the verified admin, and temporarily processes visiting card uploads with Gemini 3.8 Flash; the source file is not saved by the app. Keep HTTPS enabled on cPanel; never put service-account JSON, passwords, Gemini keys, or private keys in the repository.

Images and documents are stored by their Firebase Storage object path in Firestore; the app requests a download URL after sign-in. Treat a Firebase download URL as a bearer link—anyone you share that link with may access the file. Keep sensitive documents restricted and do not share their download URLs.

## GitHub Pages
The GitHub Pages workflow publishes only the static HTML/CSS/JS files and deliberately excludes `.php` source and the PHP upload endpoint. GitHub Pages cannot execute PHP; use the cPanel domain for PHP pages and uploads.

Use the `https://vokampagol.github.io/sapaharxpress/` address only for the static GitHub Pages version. To run PHP, open the same app on the cPanel domain using `mobile.php`; `.html` URLs on that Apache/cPanel site redirect to their `.php` counterparts when `.htaccess` is enabled.
