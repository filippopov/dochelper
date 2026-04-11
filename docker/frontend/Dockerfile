FROM node:20-bookworm-slim

# Set working directory
WORKDIR /app

# Copy package files first for better layer caching
COPY frontend/package*.json ./

# Install dependencies
RUN npm install --no-audit --no-fund --legacy-peer-deps

# Copy application source
COPY frontend/ .

EXPOSE 5173

# Start Vite dev server
CMD ["npm", "run", "dev"]
