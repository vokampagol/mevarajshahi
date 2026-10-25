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
The app uses Firebase Authentication and Cloud Firestore on the free Spark plan. GitHub Pages hosts the static files; GitHub itself is not the app's database.

1. Create a Firebase project and add a Web app in Firebase Console.
2. Under Authentication > Sign-in method, enable **Email/Password** and **Google**.
3. Under Authentication > Settings > Authorized domains, add your GitHub Pages domain.
4. Create a Cloud Firestore database.
5. Copy the Web app config into `firebase-config.js`, replacing each `PASTE_...` value. The Firebase Web config is intended for browser use; access to app data is enforced by `firestore.rules`.
6. Create the admin account using Google sign-in with `sapaharitsolution@gmail.com`, or add that email under Authentication > Users. Verify the email if using email/password.
7. Publish the contents of `firestore.rules` in Firestore > Rules, then publish the site from the `main` branch.
8. Sign in with the admin account first. On first sign-in, the app initializes the shared products and shops from its existing browser data (or the built-in catalog). Other users can then sign in and use the shared data.

The configured admin email is `sapaharitsolution@gmail.com`. The Firestore rules enforce admin-only product/shop management and allow signed-in users to create sales and decrement stock. Do not replace the rules with public read/write rules.

Accounts are managed in Firebase Authentication; this static app does not provide public account registration. Password reset is available from the sign-in page. Google sign-in is the recommended way for the admin to use that Gmail account.

## Run locally
Serve the folder locally (Firebase Auth does not work reliably from a `file://` URL) with:

```bash
python -m http.server 8000
```

Then visit `http://localhost:8000` and add `localhost` to Firebase's authorized domains.

## Deploy to GitHub Pages
This project is ready to be published as a static site from the `main` branch.

1. Push this repository to GitHub.
2. Open the repository on GitHub.
3. Go to Settings > Pages.
4. Select "Deploy from a branch".
5. Choose `main` and `/ (root)`.
6. Save.

Your site will be published at:

`https://<your-username>.github.io/<your-repository-name>/`
