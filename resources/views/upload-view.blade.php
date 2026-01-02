<!DOCTYPE html>
<html>

<head>
    <title>Payment Processor</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h2 class="text-2xl font-bold mb-4">Upload Payments CSV</h2>

        <form action="/api/upload-payments" method="POST" enctype="multipart/form-data">
            <input type="file" name="file" accept=".csv"
                class="block w-full text-sm text-gray-500 mb-4 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                Process Payments
            </button>
        </form>
    </div>
</body>

</html>
