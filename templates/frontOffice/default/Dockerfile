FROM node:14 as thelia_encore
ENV NODE_ENV development
EXPOSE 8081

# COPY package.json yarn.lock ./

RUN set -eux; \
	yarn --production=false

COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint-node
RUN chmod +x /usr/local/bin/docker-entrypoint-node

COPY . ./

ENTRYPOINT ["docker-entrypoint-node"]
CMD ["yarn", "start"]


