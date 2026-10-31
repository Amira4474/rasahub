# The RasaHub print gateway: Node only, no packages to install.
FROM node:22.11.0-alpine3.20

WORKDIR /gateway
COPY package.json ./
COPY src ./src

USER node
CMD ["node", "src/index.js"]
