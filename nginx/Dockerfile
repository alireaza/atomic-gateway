FROM nginx:1.20.2-alpine

CMD ["/bin/sh" , "-c" , "for conf in $(find /etc/nginx/conf.d.tmp -maxdepth 1 -type f); do envsubst '$STORAGE_DIRECTORY' < $conf > /etc/nginx/conf.d/$(basename $conf); done && exec nginx -g 'daemon off;'"]
