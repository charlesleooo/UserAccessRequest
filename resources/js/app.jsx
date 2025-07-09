import React from 'react';
import { createRoot } from 'react-dom/client';

// Main App Component
function App() {
    return (
        <div className="min-h-screen bg-gray-100 py-8">
            <div className="max-w-4xl mx-auto px-4">
                <div className="bg-white rounded-lg shadow-lg p-6">
                    <h1 className="text-3xl font-bold text-gray-800 mb-4">
                        User Access Request System
                    </h1>
                    <p className="text-gray-600 mb-6">
                        React frontend is now configured and working with Vite!
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div className="bg-blue-50 p-4 rounded-lg">
                            <h3 className="font-semibold text-blue-800">✅ React</h3>
                            <p className="text-blue-600 text-sm">Frontend framework ready</p>
                        </div>
                        <div className="bg-green-50 p-4 rounded-lg">
                            <h3 className="font-semibold text-green-800">✅ Vite</h3>
                            <p className="text-green-600 text-sm">Build tool configured</p>
                        </div>
                        <div className="bg-purple-50 p-4 rounded-lg">
                            <h3 className="font-semibold text-purple-800">✅ Assets</h3>
                            <p className="text-purple-600 text-sm">Manifest generation working</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Initialize React app when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const appElement = document.getElementById('react-app');
    if (appElement) {
        const root = createRoot(appElement);
        root.render(<App />);
    }
});

export default App;