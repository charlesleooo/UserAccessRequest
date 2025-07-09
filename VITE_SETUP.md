# User Access Request - Vite + React Setup

This document explains how to use the new Vite + React configuration in the User Access Request system.

## Quick Start

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Development mode (with hot reload):**
   ```bash
   npm run dev
   ```
   This starts the Vite dev server on http://localhost:5173

3. **Production build:**
   ```bash
   npm run build
   ```
   This generates optimized assets in `public/build/`

## File Structure

```
├── package.json              # Node.js dependencies and scripts
├── vite.config.js            # Vite configuration
├── vite_helpers.php          # PHP utilities for Vite integration
├── resources/
│   ├── js/
│   │   └── app.jsx          # React entry point
│   └── views/
│       └── react-example.blade.php  # Example template
├── public/
│   └── build/               # Generated assets (after npm run build)
│       ├── assets/          # JS/CSS files
│       └── .vite/
│           └── manifest.json # Asset manifest
└── react-test.php           # Working example page
```

## Integration with Existing PHP Pages

To add React components to existing PHP pages:

1. **Include the Vite helpers:**
   ```php
   <?php include 'vite_helpers.php'; ?>
   ```

2. **Add Vite assets to your HTML head:**
   ```php
   <?php echo vite_assets(['resources/js/app.jsx']); ?>
   ```

3. **Add a React mount point:**
   ```html
   <div id="react-app"></div>
   ```

## Example Integration

See `react-test.php` for a complete working example.

## Development vs Production

- **Development:** Vite dev server provides hot reload and fast builds
- **Production:** Static assets are generated and served from `public/build/`

## Error Resolution

The original "Unable to locate file in Vite manifest: resources/js/app.jsx" error is now resolved by:

1. ✅ Proper Vite configuration in `vite.config.js`
2. ✅ React entry point at `resources/js/app.jsx`
3. ✅ Correct manifest generation in `public/build/.vite/manifest.json`
4. ✅ PHP helpers for loading Vite assets