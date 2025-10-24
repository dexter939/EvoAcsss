#!/bin/bash

# K6 Installation Script for ACS Load Testing
# Installs K6 load testing tool on Linux systems

set -e  # Exit on error

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  K6 Load Testing Tool - Installation Script"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    echo "❌ Cannot detect OS"
    exit 1
fi

echo "Detected OS: $OS"
echo ""

# K6 version to install
K6_VERSION="v0.48.0"

# Install K6
case $OS in
    ubuntu|debian)
        echo "Installing K6 via APT repository..."
        sudo gpg -k
        sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
        echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
        sudo apt-get update
        sudo apt-get install k6 -y
        ;;
        
    centos|rhel|fedora)
        echo "Installing K6 via YUM repository..."
        sudo dnf install https://dl.k6.io/rpm/repo.rpm
        sudo dnf install k6 -y
        ;;
        
    alpine)
        echo "Installing K6 via APK..."
        apk add k6 --repository=https://dl-cdn.alpinelinux.org/alpine/edge/community
        ;;
        
    *)
        echo "Installing K6 from binary release..."
        echo "Version: $K6_VERSION"
        
        # Download K6 binary
        cd /tmp
        wget -q https://github.com/grafana/k6/releases/download/${K6_VERSION}/k6-${K6_VERSION}-linux-amd64.tar.gz
        
        # Extract
        tar -xzf k6-${K6_VERSION}-linux-amd64.tar.gz
        
        # Install to /usr/local/bin
        sudo mv k6-${K6_VERSION}-linux-amd64/k6 /usr/local/bin/k6
        sudo chmod +x /usr/local/bin/k6
        
        # Cleanup
        rm -rf k6-${K6_VERSION}-linux-amd64*
        
        echo "✅ K6 binary installed to /usr/local/bin/k6"
        ;;
esac

# Verify installation
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Verification"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v k6 &> /dev/null; then
    echo "✅ K6 installation successful!"
    echo ""
    k6 version
    echo ""
    echo "Usage:"
    echo "  k6 run tests/Load/scenarios/api-rest.js"
    echo "  k6 run tests/Load/scenarios/tr069.js"
    echo "  k6 run tests/Load/scenarios/mixed.js"
    echo ""
else
    echo "❌ K6 installation failed"
    exit 1
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Installation Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
