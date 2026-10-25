# MEVA Rajshahi Zone

Static POS and inventory management web app for MEVA Rajshahi Zone.

## Features
- Home page with email/password and Google sign-in
- Password reset by email
- Dashboard overview
- POS / sales flow
- Shared Firebase inventory and sales data
- Outlet listing
- Invoice modal

## Firebase setup
The app uses Firebase Authentication, Cloud Firestore Standard, and Firebase Storage. GitHub Pages can only host the static `.html` files; PHP endpoints must run on cPanel.

1. Create a Firebase project and add a Web app in Firebase Console.
2. Under Authentication > Sign-in method, enable **Email/Password** and **Google**.
3. Under Authentication > Settings > Authorized domains, add your GitHub Pages domain.
4. Create a Cloud Firestore database.
5. Copy the Web app config into `firebase-config.js`, replacing each `PASTE_...` value. The Firebase Web config is intended for browser use; access to app data is enforced by `firestore.rules`.
6. Create the admin account using Google sign-in with `sapaharitsolution@gmail.com`, or add that email under Authentication > Users. Verify the email if using email/password.
7. Enable Firebase Storage, then publish `firestore.rules` in Firestore > Rules and `storage.rules` in Storage > Rules.
8. Sign in with the verified admin account. On first sign-in, the app initializes products and shops from its existing browser data (or the built-in catalog).

The configured admin email is `sapaharitsolution@gmail.com`. For safety, the current prototype limits Firestore and Storage access—including POS sales—to that verified admin. Add staff only after setting an explicit staff allowlist and reviewing the data access rules. Do not replace the rules with public read/write rules.

Accounts are managed in Firebase Authentication; this static app does not provide public account registration. Password reset is available from the sign-in page. Google sign-in is the recommended way for the admin to use that Gmail account.

## Run locally
Serve the folder locally (Firebase Auth does not work reliably from a `file://` URL) with:

```bash
python -m http.server 8000
```

Then visit `http://localhost:8000/index.html` and add `localhost` to Firebase's authorized domains. Uploads require a PHP-capable server and are not available from GitHub Pages or Python's static server.

## cPanel PHP deployment
The PHP pages wrap the existing HTML templates so the static Pages copy can remain available. Copy the following runtime files and folders into the cPanel domain's document root (usually `public_html`):

- `index.php`, `mobile.php`, `index.html`, and `mobile.html`
- `style.css`, `firebase-config.js`, `.htaccess`, and `api/`

Then:

1. In cPanel, select PHP 8.1+ for the domain and enable the `curl` and `fileinfo` PHP extensions.
2. Make sure Apache `mod_rewrite` and `.htaccess` overrides are enabled. `index.php` is the cPanel entry point; the PHP wrappers include the HTML templates beside them.
3. Confirm `api/config.php` contains the same Storage bucket as `firebase-config.js`.
4. In Firebase Console, enable Storage. In Authentication > Settings > Authorized domains, add the cPanel hostname.
5. Publish the repository's `firestore.rules` in Firestore > Rules and `storage.rules` in Storage > Rules. These rule files can stay in the repository and do not need to be uploaded to the public document root.
6. Ensure PHP `upload_max_filesize` is at least `10M` and `post_max_size` is at least `12M`. The endpoint separately limits images to 5 MB and PDFs to 10 MB, checks MIME from file contents, and generates random object names. Firebase Storage validates the Firebase ID token, and its rules permit only the verified admin.
7. Open `https://your-domain/index.php`, sign in as `sapaharitsolution@gmail.com`, and try shop edit/logo/document or product image upload.

`api/upload.php` sends the uploaded file directly to Firebase Storage using the authenticated Firebase token; Storage Rules are the authorization gate. This avoids a server API key or Firebase service-account private key and does not require a PHP dependency install. Keep HTTPS enabled on cPanel; never put service-account JSON, passwords, or private keys in the repository.

Images and documents are stored by their Firebase Storage object path in Firestore; the app requests a download URL after sign-in. Treat a Firebase download URL as a bearer link—anyone you share that link with may access the file. Keep sensitive documents restricted and do not share their download URLs.

## GitHub Pages
The GitHub Pages workflow publishes only the static HTML/CSS/JS files and deliberately excludes `.php` source and the PHP upload endpoint. GitHub Pages cannot execute PHP; use the cPanel domain for PHP pages and uploads.
