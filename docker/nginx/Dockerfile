FROM nginx:alpine

# Remove default configuration
RUN rm /etc/nginx/conf.d/default.conf

# Copy custom Nginx configuration template
COPY default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
