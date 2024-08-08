document.addEventListener('DOMContentLoaded', function() {
    const { registerCheckoutFilters, extensionCartUpdate } = window.wc.blocksCheckout;

    // Function to modify the item name and include a quantity selector and delete icon
    const modifyItemName = (defaultValue, extensions, args) => {
        const isSummaryContext = args?.context === 'summary';

        if (!isSummaryContext) {
            return defaultValue;
        }

        // Retrieve the current quantity of the cart item
        const quantity = args?.cartItem?.quantity || 1;

        // Create the HTML for the quantity selector and delete icon
        const quantitySelector = `
            <div class="quantity-selector">
                <label for="quantity-${args.cartItem.id}">Quantity:</label>
                <input type="number" id="quantity-${args.cartItem.id}" name="quantity-${args.cartItem.id}" 
                    value="${quantity}" min="1" max="${args.cartItem.quantity_limits?.maximum || 10}" 
                    data-item-id="${args.cartItem.id}" 
                    class="quantity-input" />
                <span class="delete-icon" data-item-id="${args.cartItem.id}" title="Remove Item"><span class="fas fa-trash-alt"></span></span>
            </div>
        `;

        // Return the modified item name with the quantity selector and delete icon
        return `${defaultValue}${quantitySelector}`;
    };

    // Register the filter
    registerCheckoutFilters('quantity-selector', {
        itemName: modifyItemName,
    });

    // Debounce function to limit the rate of function execution
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Lock to prevent simultaneous updates and store pending updates
    let isUpdating = false;
    let pendingUpdate = null;

    // Handle quantity change and send data to the server with debouncing
    const handleQuantityChange = debounce(function(event) {
        if (event.target.classList.contains('quantity-input')) {
            const itemId = event.target.getAttribute('data-item-id');
            const quantity = event.target.value;

            if (isUpdating) {
                pendingUpdate = { itemId, quantity };
                return; // Queue the update
            }

            isUpdating = true;

            extensionCartUpdate({
                namespace: 'quantity-selector',
                data: {
                    itemId: itemId,
                    quantity: quantity
                },
            }).then(response => {
                // Process the next pending update if any
                if (pendingUpdate) {
                    const nextUpdate = pendingUpdate;
                    pendingUpdate = null;
                    handleQuantityChange({ target: { classList: { contains: () => true }, getAttribute: () => nextUpdate.itemId, value: nextUpdate.quantity } });
                }
            }).finally(() => {
                isUpdating = false; // Release the lock after update
            });
        }
    }, 1000); // Debounce delay of 1000ms

    // Handle quantity change event
    document.addEventListener('change', handleQuantityChange);

    // Handle delete icon click and send data to the server
    document.addEventListener('click', function(event) {
        if (event.target.closest('.delete-icon')) { // Ensure closest .delete-icon is targeted
            const itemId = event.target.closest('.delete-icon').getAttribute('data-item-id'); // Use closest to get parent delete-icon's data-item-id

            extensionCartUpdate({
                namespace: 'quantity-selector',
                data: {
                    itemId: itemId,
                    action: 'delete'
                },
            });
        }
    });
});
