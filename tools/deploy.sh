#!/usr/bin/env bash
#
# Push a release to the client's server over FTPS.
#
# Credentials never live in this file. Either export them, or drop them in
# tools/deploy.env (gitignored by the *.env rule):
#
#   SBX_FTP_USER=taherh
#   SBX_FTP_PASS=…
#   SBX_FTP_ROOT=/public_html      # optional; probed for if unset
#
#   bash tools/deploy.sh            upload
#   bash tools/deploy.sh --list     just show the layout, change nothing
#
# index.html is deliberately NOT uploaded. It is generated: the dashboard
# writes it from template.html plus whatever the studio has edited, so
# overwriting it here would drop those edits. When this finishes, sign in to
# the dashboard, open Clients and press "Save & publish" — that regenerates
# index.html from the new template with every override intact.

set -euo pipefail

cd "$(dirname "$0")/.."

[ -f tools/deploy.env ] && . tools/deploy.env

: "${SBX_FTP_USER:?set SBX_FTP_USER}"
: "${SBX_FTP_PASS:?set SBX_FTP_PASS}"

# The FTP node answers with the host's own wildcard certificate rather than one
# for the site's domain, so TLS is verified against that name while the
# connection is pinned to the address the site actually resolves to.
SBX_FTP_HOST="${SBX_FTP_HOST:-ftp.uk.cloudlogin.co}"
SBX_FTP_IP="${SBX_FTP_IP:-94.136.170.157}"

CURL=(curl --ssl-reqd
      --resolve "$SBX_FTP_HOST:21:$SBX_FTP_IP"
      --user "$SBX_FTP_USER:$SBX_FTP_PASS"
      --max-time 60 --silent --show-error)

ftp_ls() {
  "${CURL[@]}" "ftp://$SBX_FTP_HOST$1/"
}

if [ "${1:-}" = "--list" ]; then
  for d in "" /public_html /domains /home /httpdocs /www; do
    echo "== ${d:-/} =="
    ftp_ls "$d" 2>&1 | head -40 || true
    echo
  done
  exit 0
fi

# Find the public root by looking for a file we know is already up there.
if [ -z "${SBX_FTP_ROOT:-}" ]; then
  for cand in "" /public_html /httpdocs /www /domains/smashboxproductions.com/public_html; do
    if ftp_ls "$cand" 2>/dev/null | grep -q 'template\.html\|index\.html'; then
      SBX_FTP_ROOT="$cand"
      break
    fi
  done
  : "${SBX_FTP_ROOT?could not find the site root — run 'bash tools/deploy.sh --list' and set SBX_FTP_ROOT}"
fi

echo "site root: ${SBX_FTP_ROOT:-/}"
echo

FILES=(
  template.html
  cms-manifest.json
  img/favicon-32.png
  img/favicon-180.png
  img/favicon-512.png
  admin/index.php
  admin/clients.php
  admin/text.php
  admin/api/content.php
  admin/api/clients.php
  admin/inc/db.php
  admin/inc/helpers.php
  admin/inc/richtext.php
  admin/inc/layout.php
  admin/inc/render.php
  admin/assets/admin.css
  admin/assets/admin.js
  admin/assets/smashbox-logo.png
  admin/assets/favicon-32.png
  admin/assets/favicon-180.png
)

fail=0
for f in "${FILES[@]}"; do
  printf '  %-38s ' "$f"
  if "${CURL[@]}" --ftp-create-dirs --fail \
       --upload-file "$f" \
       "ftp://$SBX_FTP_HOST$SBX_FTP_ROOT/$f"; then
    echo 'ok'
  else
    echo 'FAILED'
    fail=1
  fi
done

echo
if [ "$fail" -eq 0 ]; then
  echo "Uploaded ${#FILES[@]} files."
else
  echo "Some files failed — see above."
fi
echo
echo "Last step: sign in at https://admin.smashboxproductions.com/,"
echo "open Clients and press Save & publish. That rewrites index.html"
echo "from the new template with the studio's existing edits intact."
