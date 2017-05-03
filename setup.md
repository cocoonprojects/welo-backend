cron for notifications


#production
*/15 *  *  *  * centos  /usr/bin/php /var/www/vhosts/welo-prod/public/index.php closepolls idea-items >> /tmp/welo-cron-idea-items.log  2>&1
*/15 *  *  *  * centos  /usr/bin/php /var/www/vhosts/welo-prod/public/index.php closepolls completed-items >> /tmp/welo-cron-completed-items.log  2>&1
5    0  *  *  * centos  /usr/bin/php /var/www/vhosts/welo-prod/public/index.php reminder >> /tmp/welo-cron-remider.log 2>&1
*/20   0  *  *  * centos  /usr/bin/php /var/www/vhosts/welo-prod/public/index.php sync >> /tmp/welo-cron-kanbanize-sync.log 2>&1
10   0  *  *  * centos  /usr/bin/php /var/www/vhosts/welo-prod/public/vendor/bin/zf.php closeshares >> /tmp/welo-cron-shares-timebox-sync.log 2>&1