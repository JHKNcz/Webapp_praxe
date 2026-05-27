FROM nginx:1.27-alpine

COPY index.html /usr/share/nginx/html/index.html
COPY css /usr/share/nginx/html/css
COPY js /usr/share/nginx/html/js

COPY nginx.cloudrun.conf.template /etc/nginx/templates/default.conf.template

ENV PORT=8080
# Placeholder defaults for local image sanity; Cloud Build trigger should always override.
ENV BACKEND_URL=https://REPLACE_WITH_BACKEND_URL
ENV WS_GATEWAY_URL=https://REPLACE_WITH_WS_GATEWAY_URL

EXPOSE 8080
