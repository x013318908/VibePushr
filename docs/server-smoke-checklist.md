# Server Smoke Checklist

Use this on a real PHP hosting environment before publishing a formal release.

## Fresh install

1. Upload `vp.php` to the server.
2. Open the file in a browser.
3. Confirm the initial setup screen appears.
4. Set an admin password.
5. Confirm you can reach the main upload screen afterward.

## Basic auth flow

1. Log out.
2. Log back in with the password you just set.
3. Confirm the upload screen loads without errors.

## Dry-run and real upload

1. Prepare a small test folder with at least one text file.
2. Run dry-run and confirm the log finishes without unexpected failures.
3. Run a real upload and confirm the log finishes successfully.
4. Open one uploaded file from the server and confirm it is accessible.

## Renamed entrypoint

1. Rename `vp.php` to a long custom filename.
2. Reopen the renamed URL.
3. Confirm login still works.
4. Confirm dry-run and real upload still work.

## Recovery flow

1. In a safe test environment, confirm the login guard recovery note is understandable.
2. If needed, remove `public_html/.vp_data/.vp_login_guard.json`.
3. Confirm access can be restored afterward.

## Final release gate

Mark the release ready only if:

- setup works
- login works
- dry-run works
- real upload works
- renamed entrypoint works
- recovery instructions are still accurate
