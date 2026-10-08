FROM node:24.21.0-trixie-slim

ARG HOST_UID=1000
ARG HOST_GID=1000

# Align the image's `node` user with the host user, and pre-create
# node_modules/ so its named volume starts out owned by that user.
RUN groupmod --non-unique --gid "${HOST_GID}" node \
    && usermod --non-unique --uid "${HOST_UID}" --gid "${HOST_GID}" node \
    && mkdir -p /var/www/html/node_modules \
    && chown -R node:node /var/www/html /home/node

USER node
WORKDIR /var/www/html
