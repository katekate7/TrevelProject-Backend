# Backend CI/CD Setup Guide

This document explains the GitHub Actions CI/CD pipeline for the Travel Project backend.

## Overview

The CI/CD pipeline consists of three main jobs:

1. **Test Job** - Runs tests, code quality checks, and security audits
2. **Security Check** - Performs security vulnerability scanning
3. **Build and Deploy** - Builds Docker image and deploys to production

## Pipeline Triggers

- **Push to main/develop branches** - Runs full pipeline
- **Pull requests to main** - Runs tests and security checks only
- **Manual trigger** - Can be triggered manually from GitHub Actions tab

## Jobs Description

### 1. Test Job

- Sets up PHP 8.2 with required extensions
- Starts MySQL 8.0 service for testing
- Installs Composer dependencies with caching
- Creates test database and runs migrations
- Loads fixtures (if available)
- Runs PHPUnit tests with coverage
- Uploads coverage reports to Codecov

### 2. Security Check

- Performs `composer audit` to check for known vulnerabilities
- Can be extended with additional security tools

### 3. Build and Deploy

- Only runs on main branch pushes
- Installs production dependencies
- Creates production environment file from secrets
- Builds and pushes Docker image to Docker Hub
- Uses Docker layer caching for faster builds
- Tags images with branch name, SHA, and 'latest'

## Required GitHub Secrets

You need to set these secrets in your GitHub repository settings:

### Application Secrets
- `APP_ENV` - Application environment (prod)
- `APP_SECRET` - Symfony app secret key
- `DATABASE_URL` - Production database URL
- `JWT_SECRET_KEY` - JWT private key path or content
- `JWT_PUBLIC_KEY` - JWT public key path or content  
- `JWT_PASSPHRASE` - JWT passphrase
- `CORS_ALLOW_ORIGIN` - Allowed CORS origins

### Docker Hub Secrets
- `DOCKERHUB_USERNAME` - Your Docker Hub username
- `DOCKERHUB_TOKEN` - Docker Hub access token (not password!)

## Setting Up Secrets

1. Go to your GitHub repository
2. Click Settings → Secrets and variables → Actions
3. Click "New repository secret"
4. Add each secret with the exact name and value

### Creating Docker Hub Token

1. Log in to Docker Hub
2. Go to Account Settings → Security
3. Click "New Access Token"
4. Give it a descriptive name and copy the token
5. Use this token as `DOCKERHUB_TOKEN` secret

## Environment Files

### .env.test
Used for testing environment with test database configuration.

### Production .env
Created dynamically from GitHub secrets during deployment.

## Database Setup

The pipeline automatically:
- Creates test database
- Runs Doctrine migrations
- Loads fixtures if available

## Testing

The pipeline runs:
- PHPUnit tests with coverage
- Code style checks (if configured)
- Security vulnerability checks

## Docker Image

The built image is tagged with:
- `latest` (for main branch)
- Branch name
- Git SHA
- Pull request number (for PRs)

## Extending the Pipeline

You can extend this pipeline by:

1. **Adding code quality tools**:
   ```yaml
   - name: Run PHPStan
     run: vendor/bin/phpstan analyse src/
   ```

2. **Adding deployment steps**:
   ```yaml
   - name: Deploy to production
     run: |
       ssh user@server "docker pull $IMAGE && docker-compose up -d"
   ```

3. **Adding notifications**:
   ```yaml
   - name: Notify Slack
     uses: 8398a7/action-slack@v3
     with:
       status: ${{ job.status }}
   ```

## Troubleshooting

### Common Issues

1. **Tests failing due to database**: Check DATABASE_URL in .env.test
2. **Docker build failing**: Verify Dockerfile path and context
3. **Secrets not working**: Ensure secret names match exactly
4. **JWT errors**: Check JWT key configuration and paths

### Debugging

1. Check GitHub Actions logs
2. Verify all required secrets are set
3. Test Docker build locally
4. Run tests locally with same environment

## Local Development

To run the same checks locally:

```bash
# Install dependencies
composer install

# Run tests
php bin/phpunit

# Run security check
composer audit

# Build Docker image
docker build -t travel-backend .
```

## Performance Optimizations

The pipeline includes several optimizations:
- Composer dependency caching
- Docker layer caching
- Parallel job execution
- Optimized autoloader for production
