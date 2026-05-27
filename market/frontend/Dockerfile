FROM nginx:1.27-alpine

COPY index.html /usr/share/nginx/html/index.html
COPY css /usr/share/nginx/html/css
COPY js /usr/share/nginx/html/js

COPY nginx.cloudrun.conf.template /etc/nginx/templates/default.conf.template

ENV PORT=8080
ENV BACKEND_URL=http://localhost:8000
ENV WS_GATEWAY_URL=http://localhost:3001

EXPOSE 8080
