#!/bin/bash

# Script di packaging per escludere file temporanei, log, environment, e dipendenze
# Genera un pacchetto pulito nella cartella superiore.

ZIP_NAME="../agency-core-$(date +%Y%m%d%H%M%S).zip"

echo "Creazione del pacchetto $ZIP_NAME in corso..."

zip -r $ZIP_NAME . \
  -x "*.env" \
  -x "vendor/*" \
  -x "node_modules/*" \
  -x "storage/logs/*" \
  -x "storage/framework/cache/*" \
  -x "storage/framework/sessions/*" \
  -x "storage/framework/views/*" \
  -x "storage/app/private/livewire-tmp/*" \
  -x "database/database.sqlite" \
  -x "public/build/*" \
  -x ".git/*"

echo "Pacchetto creato con successo: $ZIP_NAME"
