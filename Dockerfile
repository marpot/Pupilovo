FROM node:22-alpine

WORKDIR /workspace/Pupilovo

RUN apk add --no-cache docker-cli docker-cli-compose \
    && addgroup -g 973 dockerhost \
    && addgroup node dockerhost

COPY frontend/package*.json ./frontend/

RUN cd frontend \
    && npm install \
    && chown -R node:node /workspace/Pupilovo/frontend/node_modules

COPY frontend/ ./frontend/

EXPOSE 5173

CMD ["npm", "--prefix", "frontend", "run", "dev", "--", "--host", "0.0.0.0"]