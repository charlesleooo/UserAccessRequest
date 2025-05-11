<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Access Request Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#E8F2FF',
                            100: '#D1E5FF',
                            200: '#A3CBFF',
                            300: '#75B1FF',
                            400: '#4797FF',
                            500: '#0084FF',  // Facebook Messenger primary blue
                            600: '#006ACC',
                            700: '#004F99',
                            800: '#003566',
                            900: '#001A33',
                        }
                    },
                    fontFamily: {
                        'poppins': ['Poppins']
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                        'card': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-card {
            backdrop-filter: blur(5px);
            background-color: rgba(255, 255, 255, 0.95);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        
        .custom-checkbox:checked {
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
        }
        
        .logo {
            max-width: 300px;
            height: auto;
        }
        
        @media (max-width: 768px) {
            .logo {
                max-width: 200px;
            }
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-6 bg-gray-50 font-poppins">
    <div class="max-w-7xl mx-auto form-card rounded-xl shadow-card overflow-hidden">
        <div class="bg-white text-black py-6 px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <img src="logo.png" alt="Logo" class="logo">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-center md:text-right">USER ACCESS REQUEST FORM</h1>
            </div>
        </div>
        
        <form action="submit.php" method="POST" id="accessRequestForm" class="p-6 md:p-8 space-y-8">
            <!-- Requestor Information -->
            <div class="bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Requestor Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Requestor Name <span class="text-red-500">*</span></label>
                        <input type="text" name="requestor_name" placeholder="Enter your full name" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Business Unit <span class="text-red-500">*</span></label>
                        <select name="business_unit" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200 bg-white">
                            <option value="">Select Business Unit</option>
                            <option value="AAC">AAC</option>
                            <option value="ALDEV">ALDEV</option>
                            <option value="ARC">ARC</option>
                            <option value="FHI">FHI</option>
                            <option value="SACI">SACI</option>
                            <option value="SAVI">SAVI</option>
                            <option value="SCCI">SCCI</option>
                            <option value="SFC">SFC</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. of Access Request <span class="text-red-500">*</span></label>
                        <input type="number" name="access_request_number" placeholder="Enter number from 1-5 only" 
                            min="1" max="5" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200"
                            title="Please enter a number between 1 to 5 only"
                            onchange="validateNumber(this)">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                        <select name="department" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200 bg-white">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" placeholder="example@domain.com" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact No. <span class="text-red-500">*</span></label>
                        <input type="tel" name="contact_number" placeholder="09XX-XXX-XXXX" required 
                            class="w-full h-12 px-4 text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200"
                            pattern="\d{11}" maxlength="11" minlength="11"
                            title="Phone number must be exactly 11 digits long">
                    </div>
                </div>
            </div>

            <!-- Access Types -->
            <div class="bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Access Type <span class="text-red-500">*</span></h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="System Application" required 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">System Application</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="PC Access - Network" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">PC Access - Network</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Email Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Email Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Server Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Server Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Internet Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Internet Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Printer Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Printer Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Active Directory Access (MS ENTRA ID)" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Active Directory Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Firewall Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Firewall Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Wi-Fi/Access Point Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Wi-Fi/Access Point Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="TNA Biometric Device Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">TNA Biometric Device Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="USB/PC-port Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">USB/PC-port Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="CCTV Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">CCTV Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="PC Access - Local" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">PC Access - Local</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="VPN Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">VPN Access</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="access_type" value="Offsite Storage Facility Access" 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Offsite Storage Facility Access</span>
                    </label>
                </div>
            </div>

            <!-- System/Application Type -->
            <div id="systemApplicationSection" class="hidden bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">System/Application Type</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Canvasing System" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Canvasing System</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="ERP/NAV" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">ERP/NAV</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Legacy Payroll" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Legacy Payroll</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="HRIS" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">HRIS</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Legacy Purchasing" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Legacy Purchasing</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Piece Rate Payroll System" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Piece Rate Payroll System</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Legacy Inventory" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Legacy Inventory</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Fresh Chilled Receiving System" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Fresh Chilled Receiving System</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Legacy Vouchering" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Legacy Vouchering</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Quickbooks" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Quickbooks</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Legacy Ledger System" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Legacy Ledger System</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="Memorandum Receipt" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">Memorandum Receipt</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="checkbox" name="system_type[]" value="ZankPOS" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700">ZankPOS</span>
                    </label>
                    <div class="col-span-1 sm:col-span-2 lg:col-span-3 xl:col-span-4 flex items-center p-3 border border-gray-200 rounded-lg">
                        <input type="checkbox" name="system_type[]" value="other" 
                            class="w-5 h-5 rounded text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 mr-4 text-gray-700">Other (specify):</span>
                        <input type="text" name="other_system_type" 
                            class="flex-1 h-10 px-3 rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200" disabled>
                    </div>
                </div>
            </div>
            
            <!-- Role Access Type -->
            <div id="roleAccessSection" class="hidden bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Role Access Type (If applicable)</h2>
                <div>
                    <textarea name="role_access_type" rows="4" 
                        class="w-full text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200 p-4 resize-none" 
                        placeholder="Enter role access type details"></textarea>
                </div>
            </div>

            <!-- Access Duration -->
            <div class="bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Access Duration <span class="text-red-500">*</span></h2>
                <div class="space-y-4">
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <input type="radio" name="duration_type" value="permanent" required 
                            class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                        <span class="ml-2 text-gray-700 text-lg">Permanent</span>
                    </label>
                    <div class="p-3 border border-gray-200 rounded-lg">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="duration_type" value="temporary" 
                                    class="w-5 h-5 text-primary-600 border-gray-300 focus:ring-primary-500">
                                <span class="ml-2 text-gray-700 text-lg">Temporary</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-2 mt-2 md:mt-0">
                                <input type="date" name="start_date" 
                                    class="h-12 px-4 text-base rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200" 
                                    disabled>
                                <span class="text-gray-700 text-lg mx-2">to</span>
                                <input type="date" name="end_date" 
                                    class="h-12 px-4 text-base rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200" 
                                    disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Justification -->
            <div class="bg-white p-6 rounded-lg shadow-soft border border-gray-100">
                <label class="block text-xl font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                    Justification for Access Request <span class="text-red-500">*</span>
                </label>
                <textarea name="justification" 
                    placeholder="Please provide a detailed reason for this access request" 
                    required rows="4" 
                    class="w-full text-lg rounded-lg border-2 border-gray-300 focus:border-primary-500 transition duration-200 p-4 resize-none"></textarea>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="reset" 
                    class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-lg font-medium">
                    Reset
                </button>
                <button type="submit" 
                    class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-200 text-lg font-medium">
                    Submit Request
                </button>
            </div>
        </form>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-xl shadow-xl max-w-md w-full mx-4 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Request Submitted!</h3>
                <p class="text-gray-600 mb-6" id="modalMessage"></p>
                <button type="button" onclick="closeModal()" 
                    class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-3 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>
    <!---------------------------------------------------------------------SCRIPT------------------------------------------------------------------------------------------------------------------------------------------------------------------------------>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const businessUnitDepartments = {
                'AAC': [
                    'AFFILIATES',
                    'APP',
                    'CATFISH GROW-OUT',
                    'ENGINEERING',
                    'FINANCE',
                    'GENSAN PROCESSING PLANT (GPP)',
                    'GROW OUT',
                    'HUMAN RESOURCE & ADMIN',
                    'INFORMATION TECHNOLOGY (IT)',
                    'LAND BASED',
                    'MANUFACTURING',
                    'MARKETING',
                    'MATERIALS MANAGEMENT',
                    'OFFICE OF THE VP-OPERATIONS',
                    'PPP-SLICING/OTHER PROCESSING',
                    'REGIONAL SALES',
                    'RPP',
                    'SALES & MARKETING',
                    'SEA CAGE',
                    'SPECIAL IMPORTATION/TRADING',
                    'TECHNICAL SERVICES',
                    'TH - CLEARING',
                    'TILAPIA HATCHERY (TH)',
                    'VAP',
                ],
                'ALDEV': [
                    'ALD Cattle',
                    'ALD Banana-San Jose',
                    'ALD Engineering',
                    'ALD Operations Services',
                    'ALD Technical Services',
                    'ALD-PROD PLANNING'
                ],
                'ARC': [
                    'ARC - NURSERY',
                    'ARC Engineering',
                    'ARC Growout',
                    'Administrative services'
                ],
                'FHI': [
                    'FIELDS',
                    'SELLING & MARKETING DEPARTMENT',
                    'OPERATIONS DEPARTMENT',
                    'OTHER SPECIE DEPARTMENT'
                ],
                'SACI': [
                    'ALDEVINCO-AGRI',
                    'FHI',
                    'ARC',
                    'SCCI',
                    'CLAFI',
                    'ALSEC',
                    'SAVI',
                    'CONAL',
                    'ABBA BLESS',
                    'ALC',
                    'SBSTG',
                    'G3 AQUAVENTURES INC',
                    'AAC',
                    'VARIOUS AFFILIATES'
                ],
                'SAVI': [
                    'SCCI',
                    'ALSEC',
                    'SUNFARMS',
                    'AAC',
                    'OPERATIONS SERVICES',
                    'BANANA OPERATION',
                    'BANANA LEAVES OPERATION',
                    'AGRI-ENGINEERING',
                    'G&A',
                    'TSD Agri',
                    'G&A - Distribution',
                    'OOM',
                    'Conal Corporation'
                ],
                'SCCI': [
                    'SCC Banana-Lanton',
                    'SCC Cattle',
                    'SCC Engineering',
                    'SCC Pineapple',
                    'SCC Technical Services',
                    'SCCI Operations Services'
                ],
                'SFC': [
                    'AGRI-ENGINEERING',
                    'AGRI-OPERATIONS SERVICES',
                    'AGRI-PINEAPPLE OPERATIONS',
                    'FIELD OVERHEAD'
                ],

            };

            const businessUnitSelect = document.querySelector('select[name="business_unit"]');
            const departmentSelect = document.querySelector('select[name="department"]');

            businessUnitSelect.addEventListener('change', function() {
                const selectedUnit = this.value;
                departmentSelect.innerHTML = '<option value="">Select Department</option>';
                
                if (selectedUnit && businessUnitDepartments[selectedUnit]) {
                    businessUnitDepartments[selectedUnit].forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept;
                        option.textContent = dept;
                        departmentSelect.appendChild(option);
                    });
                }
            });

            const form = document.getElementById('accessRequestForm');
            const durationType = form.querySelectorAll('input[name="duration_type"]');
            const startDate = form.querySelector('input[name="start_date"]');
            const endDate = form.querySelector('input[name="end_date"]');
            const systemApplicationSection = document.getElementById('systemApplicationSection');
            const roleAccessSection = document.getElementById('roleAccessSection');
            const accessTypeInputs = form.querySelectorAll('input[name="access_type"]');
            const otherSystemTypeCheckbox = form.querySelector('input[value="other"]');
            const otherSystemTypeInput = form.querySelector('input[name="other_system_type"]');

            // Add date validation
            const startDateInput = form.querySelector('input[name="start_date"]');
            const endDateInput = form.querySelector('input[name="end_date"]');

            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                });

                endDateInput.addEventListener('change', function() {
                    startDateInput.max = this.value;
                });
            }

            // Function to reset form sections
            function resetFormSections() {
                // Reset and hide system application section
                systemApplicationSection.classList.add('hidden');
                form.querySelectorAll('input[name="system_type[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
                otherSystemTypeInput.value = '';
                otherSystemTypeInput.disabled = true;

                // Reset and hide role access section
                roleAccessSection.classList.add('hidden');
                form.querySelector('textarea[name="role_access_type"]').value = '';

                // Reset access duration section
                startDate.disabled = true;
                startDate.value = '';
                endDate.disabled = true;
                endDate.value = '';
            }

            // Handle Other System Type input
            otherSystemTypeCheckbox?.addEventListener('change', function() {
                otherSystemTypeInput.disabled = !this.checked;
                if (this.checked) {
                    otherSystemTypeInput.required = true;
                } else {
                    otherSystemTypeInput.required = false;
                    otherSystemTypeInput.value = '';
                }
            });

            // Handle Access Type selection
            accessTypeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    resetFormSections();
                    if (this.value === 'System Application') {
                        systemApplicationSection.classList.remove('hidden');
                    } else if (this.value === 'role_access') {
                        roleAccessSection.classList.remove('hidden');
                    }
                });
            });

            durationType.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'temporary') {
                        startDate.disabled = false;
                        endDate.disabled = false;
                        startDate.required = true;
                        endDate.required = true;
                    } else {
                        startDate.disabled = true;
                        endDate.disabled = true;
                        startDate.required = false;
                        endDate.required = false;
                        startDate.value = '';
                        endDate.value = '';
                    }
                });
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const accessTypes = form.querySelectorAll('input[name="access_type"]:checked');
                if (accessTypes.length === 0) {
                    alert('Please select an Access Type');
                    return;
                }

                // Validate system application specific fields
                if (accessTypes[0].value === 'system_application') {
                    // Validate system types
                    const systemTypes = form.querySelectorAll('input[name="system_type[]"]:checked');
                    if (systemTypes.length === 0) {
                        alert('Please select at least one System/Application Type');
                        return;
                    }

                    // Validate access duration
                    const durationTypeSelected = form.querySelector('input[name="duration_type"]:checked');
                    if (!durationTypeSelected) {
                        alert('Please select an Access Duration');
                        return;
                    }

                    if (durationTypeSelected.value === 'temporary') {
                        if (!startDate.value || !endDate.value) {
                            alert('Please enter both start and end dates');
                            return;
                        }
                    }
                }

                // Submit form using fetch
                const formData = new FormData(this);
                fetch('submit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal
                        document.getElementById('modalMessage').textContent = data.message;
                        document.getElementById('successModal').classList.remove('hidden');
                        // Reset form
                        form.reset();
                        resetFormSections();
                    } else {
                        alert(data.message || 'An error occurred while submitting the form.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting the form.');
                });
            });

            // Modal close function
            window.closeModal = function() {
                document.getElementById('successModal').classList.add('hidden');
            }
        });
        //number of access validation
        function validateNumber(input) {
            const value = parseInt(input.value);
            if (value < 1 || value > 5) {
                alert('Please enter a number between 1 and 5.');
                input.value = '';
            }
            }
    </script>
</body>
</html>