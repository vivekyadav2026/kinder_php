# Mobile App Wrapper for Karigor Ledger Pro PHP

This directory contains a **Capacitor** wrapper to run your PHP application inside a native Android APK.

## Prerequisites
1. Node.js installed on your PC.
2. Android Studio installed on your PC.
3. Your local server running (XAMPP).

## Steps to Generate the APK

### Step 1: Open Terminal in this folder
```bash
cd c:\xampp\htdocs\kinder_php\mobile_app
```

### Step 2: Install dependencies
```bash
npm install
```

### Step 3: Configure your local IP Address
1. Open `capacitor.config.json` in an editor.
2. Replace `"http://192.168.1.5/kinder_php/"` with the **IP address** of your computer on your local Wi-Fi network.
   * *To find your IP: Open Command Prompt (CMD) and run `ipconfig`. Look for the "IPv4 Address" (e.g. `192.168.1.XX`).*

### Step 4: Initialize and Open in Android Studio
1. Add the Android platform:
   ```bash
   npx cap add android
   ```
2. Sync the project:
   ```bash
   npx cap sync
   ```
3. Open the project in Android Studio:
   ```bash
   npx cap open android
   ```

### Step 5: Build the APK in Android Studio
1. Once Android Studio opens and indexes the project, click on **Build** in the top menu.
2. Select **Build Bundle(s) / APK(s)** &rarr; **Build APK(s)**.
3. Once completed, a popup will show with a "Locate" link. Click it to find your installable **`app-debug.apk`**!
