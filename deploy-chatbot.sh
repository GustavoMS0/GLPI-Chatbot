#!/usr/bin/env bash
# =============================================================================
#  deploy-chatbot.sh - Atualiza o GLPI Chatbot a partir da cópia local
#  (sem GitHub) e corrige os timezones do banco.
#  Uso: sudo bash deploy-chatbot.sh
# =============================================================================
set -Eeuo pipefail

SRC="/home/martins/.gemini/antigravity/scratch/GLPI-Chatbot/glpichatbot"
GLPI_DIR="/var/www/glpi"
DEST="$GLPI_DIR/plugins/glpichatbot"

[[ $EUID -eq 0 ]] || { echo "Execute como root: sudo bash $0"; exit 1; }
[[ -f $SRC/setup.php ]] || { echo "Plugin não encontrado em $SRC"; exit 1; }
[[ -f $GLPI_DIR/bin/console ]] || { echo "GLPI não encontrado em $GLPI_DIR"; exit 1; }

console() { runuser -u www-data -- php "$GLPI_DIR/bin/console" "$@"; }

echo "==> Copiando o plugin para $DEST"
rm -rf "$DEST"
cp -r "$SRC" "$DEST"
chown -R root:root "$DEST"
chmod -R u=rwX,go=rX "$DEST"

echo "==> Instalando/atualizando e ativando"
console plugin:install --username=glpi --force --no-interaction glpichatbot
console plugin:activate --no-interaction glpichatbot || true

echo "==> Timezones do banco"
TZ_TOOL=$(command -v mariadb-tzinfo-to-sql || command -v mysql_tzinfo_to_sql || true)
if [[ -n $TZ_TOOL ]]; then
  "$TZ_TOOL" /usr/share/zoneinfo 2>/dev/null | mariadb mysql
  mariadb -e "GRANT SELECT ON mysql.time_zone_name TO 'glpi'@'localhost'; FLUSH PRIVILEGES;" || true
  console database:enable_timezones --no-interaction || true
fi

echo "==> Limpando cache"
console cache:clear --no-interaction || true

VERSION=$(grep -oP "PLUGIN_GLPICHATBOT_VERSION', '\K[^']+" "$DEST/setup.php")
echo
echo "GLPI Chatbot $VERSION implantado."
echo "Tela dedicada: http://localhost/plugins/glpichatbot/front/chatbot.php"
echo "Recarregue o navegador com Ctrl+F5."

