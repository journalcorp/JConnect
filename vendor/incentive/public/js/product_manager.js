// public/js/product_manager.js

document.addEventListener('DOMContentLoaded', () => {
    const productModal = document.getElementById('productModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelFormBtn = document.getElementById('cancelFormBtn');
    const addProductBtn = document.getElementById('addProductBtn');
    const productForm = document.getElementById('productForm');
    const modalTitle = document.getElementById('modalTitle');

    // Form input elements
    const productIdInput = document.getElementById('productId');
    const productNameInput = document.getElementById('productName');
    const productGroupInput = document.getElementById('productGroup'); // Changed from productCategoryInput
    const specialIncentivePercentInput = document.getElementById('specialIncentivePercent');

    // Function to set the disabled state of input fields in the modal
    function setFormInputsState(isEditMode) {
        // These are the fields that should be disabled when in "edit" mode,
        // except for specialIncentivePercentInput.
        const inputsToDisable = [
            productNameInput,
            productGroupInput // Now controlling productGroupInput
        ];

        inputsToDisable.forEach(input => {
            if (isEditMode) {
                input.setAttribute('disabled', 'true');
                input.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                input.removeAttribute('disabled');
                input.classList.remove('bg-gray-100', 'cursor-not-allowed');
            }
        });

        // The specialIncentivePercentInput should always be enabled for modification
        specialIncentivePercentInput.removeAttribute('disabled');
        specialIncentivePercentInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
    }


    // Event listener for "เพิ่มสินค้าใหม่" (Add New Product) button
    addProductBtn.addEventListener('click', () => {
        modalTitle.textContent = 'เพิ่มสินค้าใหม่';
        productIdInput.value = ''; // Clear ID for new entry
        productForm.reset(); // Clear all form fields
        setFormInputsState(false); // Enable all fields for adding a new product
        productModal.classList.remove('hidden'); // Show the modal
    });

    // Event listener for closing the modal using the 'x' button
    closeModalBtn.addEventListener('click', () => {
        productModal.classList.add('hidden'); // Hide the modal
    });

    // Event listener for the "ยกเลิก" (Cancel) button inside the form
    cancelFormBtn.addEventListener('click', () => {
        productModal.classList.add('hidden'); // Hide the modal
    });


    // Event listener for all "แก้ไข" (Edit) buttons in the product table
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            const row = event.target.closest('tr'); // Get the closest table row to the clicked button

            // Extract data from the specific cells using their class names
            const id = row.querySelector('.product-code p')?.textContent.trim() || '';
            const name = row.querySelector('.product-name p')?.textContent.trim() || '';
            const group = row.querySelector('.group p')?.textContent.trim() || ''; // Get 'กลุ่มสินค้า'
            // Ensure special incentive is extracted correctly (remove '%' and parse to float)
            // Note: The product table in products.php doesn't currently display a 'special-incentive-percent' column.
            // If this data isn't directly in the row, you might need to fetch it via an API call based on the product ID.
            // For now, it will default to 0 if not found in the HTML.
            const specialIncentiveText = row.querySelector('.special-incentive-percent p')?.textContent.trim() || '0%';
            const specialIncentive = parseFloat(specialIncentiveText.replace('%', ''));


            // Populate the modal form fields with the extracted data
            productIdInput.value = id;
            productNameInput.value = name;
            productGroupInput.value = group; // Set the product group
            specialIncentivePercentInput.value = specialIncentive;

            // Set modal title and form input states
            modalTitle.textContent = `แก้ไขสินค้า:`;
            setFormInputsState(true); // Disable most fields, enable only incentive
            productModal.classList.remove('hidden'); // Show the modal
        });
    });

    // Event listener for form submission (handles both Add and Edit)
    productForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // Prevent default browser form submission

        const id = productIdInput.value;
        const special_incentive_percent = parseFloat(specialIncentivePercentInput.value);

        if (id) {
            // This is an EDIT operation (productId has a value)
            try {
                // IMPORTANT: Make sure this URL is correct for your API endpoint
                // It should point to a PHP file that handles updating product data in your database
                const response = await fetch('../api/update_product.php', {
                    method: 'POST', // Using POST, but PUT/PATCH are more RESTful for updates
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        special_incentive_percent: special_incentive_percent
                    })
                });
                const result = await response.json(); // Assuming your PHP API returns JSON

                if (response.ok && result.success) { // Check both HTTP status and API's custom success flag
                    alert('แก้ไขข้อมูลสินค้าสำเร็จ!');
                    window.location.reload(); // Reload the page to show updated data in the table
                } else {
                    alert('เกิดข้อผิดพลาดในการแก้ไข: ' + (result.message || 'Unknown error. Check server logs.'));
                    console.error('API Error:', result.message || 'No message from API.');
                }
            } catch (error) {
                console.error('Error during product update:', error);
                alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ หรือเกิดข้อผิดพลาดทางเทคนิค: ' + error.message);
            }
        } else {
            // This is an ADD operation (productId is empty)
            const name = productNameInput.value;
            const group = productGroupInput.value; // Get the product group
            // Price and Stock are removed from the form, so they won't be sent here.
            // If they are required for adding, you'll need to re-evaluate or set default values server-side.

            const productData = {
                name: name,
                group: group, // Use 'group' instead of 'category'
                special_incentive_percent: special_incentive_percent,
                // price and stock are no longer here
            };

            try {
                // IMPORTANT: Make sure this URL is correct for your API endpoint
                // It should point to a PHP file that handles adding product data to your database
                const response = await fetch('../api/add_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(productData)
                });
                const result = await response.json(); // Assuming your PHP API returns JSON

                if (response.ok && result.success) { // Check both HTTP status and API's custom success flag
                    alert('เพิ่มสินค้าใหม่สำเร็จ!');
                    window.location.reload(); // Reload the page to show the newly added data
                } else {
                    alert('เกิดข้อผิดพลาดในการเพิ่ม: ' + (result.message || 'Unknown error. Check server logs.'));
                    console.error('API Error:', result.message || 'No message from API.');
                }
            } catch (error) {
                console.error('Error during product add:', error);
                alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ หรือเกิดข้อผิดพลาดทางเทคนิค: ' + error.message);
            }
        }
        productModal.classList.add('hidden'); // Always hide the modal after form submission attempt
    });

    // Handle Delete button click (If you have delete buttons in your products.php table)
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', async (event) => {
            const id = event.target.dataset.id; // Assuming data-id attribute is set on delete buttons
            const confirmDelete = confirm(`คุณแน่ใจหรือไม่ที่จะลบสินค้า ID: ${id}?`);

            if (confirmDelete) {
                // In a real system, you'd send an API DELETE request here
                // Example:
                // try {
                //     const response = await fetch(`/api/delete_product.php?id=${id}`, { // Adjust URL
                //         method: 'GET', // Or 'DELETE'
                //         headers: { 'Content-Type': 'application/json' }
                //     });
                //     const result = await response.json();
                //     if (response.ok && result.success) {
                //         alert('ลบสินค้าสำเร็จ!');
                //         window.location.reload(); // Reload or remove row from DOM
                //     } else {
                //         alert('เกิดข้อผิดพลาดในการลบ: ' + (result.message || 'Unknown error'));
                //     }
                // } catch (error) {
                //     console.error('Error during product delete:', error);
                //     alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
                // }
                alert(`(Demo) กำลังลบสินค้า ID: ${id}`); // Placeholder for demonstration
                // For a real application, replace the alert with actual API call and reload/DOM manipulation
            }
        });
    });
});