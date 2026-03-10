#!/bin/bash
# Install Linux desktop dependencies for Flutter (required for flutter run -d linux).
# Run with: bash scripts/install-flutter-linux-deps.sh
# You will be prompted for your sudo password.
set -e
echo "Installing Flutter Linux desktop dependencies..."
sudo apt-get update
sudo apt-get install -y clang cmake ninja-build pkg-config libgtk-3-dev build-essential
echo "Done. You can now use: flutter run -d linux"
