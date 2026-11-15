# File Storage Migration Guide — Local Uploads to Cloudflare R2

Homeo-Hall stores only two kinds of permanent uploads:

| Type | Local location | R2 object key | Database reference |
|---|---|---|---|
| Chamber logo | `hpms/storage/app/public/branding/...` | `branding/...` | `settings.chamber_logo` |
| Derived icons/splash | `hpms/storage/app/public/branding/...` | `branding/...` | `settings.chamber_brand_assets` |
| User avatar | `hpms/storage/app/public/avatars/...` | `avatars/...` | `users.avatar_path` |

There are no other uploads. Temporary files (`storage/app/pdf`,
`storage/app/brand-assets`, backups) are recreated on demand and must not be
migrated. The bucket stays **private**; the application streams files through
`/media/{path}` (branding is readable for login/PWA; avatars require a session).

## 1. Pre-flight

1. Confirm the R2 bucket and API token from
   [`DEPLOYMENT_GUIDE.md`](DEPLOYMENT_GUIDE.md) steps 3–4 work:
   ```bash
   export AWS_ACCESS_KEY_ID='<R2_ACCESS_KEY_ID>'
   export AWS_SECRET_ACCESS_KEY='<R2_SECRET_ACCESS_KEY>'
   export AWS_DEFAULT_REGION=auto
   export AWS_BUCKET=homeo-hall-files
   export AWS_ENDPOINT=https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com
   export AWS_USE_PATH_STYLE_ENDPOINT=true

   aws s3 ls "s3://$AWS_BUCKET" --endpoint-url "$AWS_ENDPOINT"
   ```
2. Record the local inventory (file counts and total bytes):
   ```bash
   cd hpms
   find storage/app/public/avatars storage/app/public/branding -type f \
     | tee /tmp/hpms-upload-files.txt | wc -l
   du -sb storage/app/public/avatars storage/app/public/branding
   ```
3. Check which database rows reference files (read-only):
   ```sql
   SELECT 'logo' kind, value path FROM settings WHERE key = 'chamber_logo'
   UNION ALL
   SELECT 'avatar', avatar_path FROM users WHERE avatar_path IS NOT NULL;
   ```
4. Do **not** delete or move the local files. They remain the rollback copy.

## 2. Dry run (mandatory)

The application includes an idempotent command. It only touches the `avatars/`
and `branding/` prefixes, never deletes source files, and refuses to copy a disk
onto itself:

```bash
cd hpms

php artisan uploads:migrate --from=public --to=s3 --dry-run
```

Expected: one `would   <path> (<bytes> bytes)` line per file and a summary
table. No object is written. If the output lists nothing, verify the local files
exist at `storage/app/public` and that `UPLOADS_DISK` is `public` locally.

## 3. Copy

```bash
php artisan uploads:migrate --from=public --to=s3
```

- Existing objects are skipped unless `--overwrite` is passed.
- Each upload is verified by re-reading its size on the destination; a mismatch
  is reported as a failure.
- On any failure the command exits non-zero and leaves the source untouched.
  Fix the error (credentials, endpoint, permissions) and run it again — it is
  safe to retry and will skip completed files.
- Credentials are read from the environment. They are never printed.

For a single prefix:

```bash
php artisan uploads:migrate --from=public --to=s3 --prefix=branding
php artisan uploads:migrate --from=public --to=s3 --prefix=avatars
```

## 4. Verify

1. Re-run the dry run; every file should now report `skip` (already exists):
   ```bash
   php artisan uploads:migrate --from=public --to=s3 --dry-run
   ```
2. Compare object counts and total bytes:
   ```bash
   aws s3api list-objects-v2 --bucket "$AWS_BUCKET" \
     --endpoint-url "$AWS_ENDPOINT" \
     --query 'Contents[].{Key:Key,Size:Size}' --output json \
     > /tmp/hpms-r2-objects.json

   php -r '$o=json_decode(file_get_contents("/tmp/hpms-r2-objects.json"),true); $n=count($o); $b=array_sum(array_column($o,"Size")); echo "$n objects, $b bytes\n";'
   ```
   Counts and bytes must match the local inventory (source totals include only
   `avatars/` and `branding/`).
3. Fetch one object through the command line to prove read access:
   ```bash
   aws s3 cp "s3://$AWS_BUCKET/branding/<known-path>" /tmp/hpms-check-logo \
     --endpoint-url "$AWS_ENDPOINT"
   ```
4. Test retrieval **through the application** after Render is configured:
   - Log in, open Settings, and confirm the existing logo/avatar previews load.
   - The `<img>` URLs must be `/media/branding/...` or `/media/avatars/...`,
     never `/storage/...`.
   - Upload a new logo and avatar, refresh, then remove them; confirm the old
     objects are deleted and the previews update.
5. Confirm the bucket is private:
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' \
     "https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com/$AWS_BUCKET/branding/<known-path>"
   ```
   Expect `401`/`403` (unsigned request). A `200` means public access is on —
   turn it off in Cloudflare. Do not make the bucket public to fix previews;
   previews use `/media/...`.

## 5. Failure and fallback behavior

- A missing object makes `/media/...` return **404**; no filesystem path or
  credential is exposed.
- Pages still render; PWA icons fall back to the bundled `/icons/...` assets
  when the stored brand-asset setting is absent.
- To recover, re-upload the logo in Settings (new key) or copy the missing file
  again with `uploads:migrate` (or `--overwrite` after checking the object).
- If R2 is unreachable, uploads fail with a form error instead of creating a
  broken database row; existing pages do not fail.

## 6. Rollback

1. Stop new uploads (put the app in maintenance) so the two stores do not
   diverge.
2. Keep all local files and all R2 objects; do not delete either side.
3. In Render, set `UPLOADS_DISK=public` **only** if the container has the files.
   Render's filesystem is ephemeral, so on Render the correct rollback is to fix
   R2 access/credentials, not to switch disks.
4. For local development, set `UPLOADS_DISK=public` and restart; the original
   files are still under `storage/app/public`.
5. Verify previews and uploads, then re-enable the service.

Never run a destructive sync (`aws s3 sync --delete`) against the bucket, and
never delete source files automatically.
