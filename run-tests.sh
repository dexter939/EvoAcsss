#!/bin/bash
# ACS Test Runner
# Fixes PHPUnit timeout issues by disabling PCNTL signal handlers

php -d pcntl.enabled=0 vendor/bin/phpunit "$@"
