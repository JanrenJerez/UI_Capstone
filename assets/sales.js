let products = JSON.parse(localStorage.getItem('gymProducts')) || [];
let cart = [];
let dailyTotal = parseFloat(localStorage.getItem('dailyTotal')) || 0;

document.addEventListener('DOMContentLoaded', () => {
    updateProductUI();
    updateDailyTotalUI();
});

function openModal(index = null) {
    const modal = document.getElementById('productModal');
    modal.style.display = "block";
    
    if (index !== null) {
        const p = products[index];
        document.getElementById('editIndex').value = index;
        document.getElementById('pname').value = p.name;
        document.getElementById('pprice').value = p.price;
        document.getElementById('pstock').value = p.stock;
    } else {
        clearModalInputs();
    }
}

function closeModal() {
    document.getElementById('productModal').style.display = "none";
}

function clearModalInputs() {
    document.getElementById('editIndex').value = "";
    document.getElementById('pname').value = "";
    document.getElementById('pprice').value = "";
    document.getElementById('pstock').value = "";
    document.getElementById('pimage').value = "";
}

function saveProduct() {
    const index = document.getElementById('editIndex').value;
    const name = document.getElementById('pname').value;
    const price = parseFloat(document.getElementById('pprice').value);
    const stock = parseInt(document.getElementById('pstock').value);
    const imageInput = document.getElementById('pimage');

    if (!name || isNaN(price) || isNaN(stock)) return alert("Fill all fields!");

    const processData = (imageUrl) => {
        const productData = { name, price, stock, imageUrl };
        if (index !== "") {
            products[index] = productData;
        } else {
            products.push(productData);
        }
        localStorage.setItem('gymProducts', JSON.stringify(products));
        updateProductUI();
        closeModal();
    };

    if (imageInput.files && imageInput.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => processData(e.target.result); // Converts to Base64
        reader.readAsDataURL(imageInput.files[0]);
    } else {
        const existingImg = index !== "" ? products[index].imageUrl : "https://via.placeholder.com/150";
        processData(existingImg);
    }
}

function deleteProduct(index) {
    if (confirm(`Are you sure you want to delete ${products[index].name}?`)) {
        products.splice(index, 1);
        localStorage.setItem('gymProducts', JSON.stringify(products));
        updateProductUI();
    }
}

function updateProductUI() {
    const productList = document.getElementById('productList');
    productList.innerHTML = "";
    
    products.forEach((product, index) => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <img src="${product.imageUrl}" alt="product" style="width:100px; height:100px; object-fit:cover;">
            <h4>${product.name}</h4>
            <p>₱${product.price.toFixed(2)}</p>
            <p><small>Stock: ${product.stock}</small></p>
            <div class="card-actions">
                <button onclick="addToCart(${index})">Add</button>
                <button onclick="openModal(${index})">Edit</button>
                <button onclick="deleteProduct(${index})">Delete</button>
            </div>
        `;
        productList.appendChild(card);
    });
}

function addToCart(index) {
    const product = products[index];
    if (product.stock <= 0) return alert("Out of stock!");

    const cartItem = cart.find(item => item.name === product.name);
    if (cartItem) {
        cartItem.qty++;
    } else {
        cart.push({ ...product, qty: 1, originalIndex: index });
    }

    product.stock--;
    updateProductUI();
    updateCartUI();
}

function updateCartUI() {
    const salesTable = document.getElementById('salesTable');
    salesTable.innerHTML = "";
    let grandTotal = 0;

    cart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        grandTotal += subtotal;
        
        salesTable.innerHTML += `
            <tr>
                <td style="display: flex; align-items: center; gap: 10px;">
                    <img src="${item.imageUrl}" alt="item" style="width:40px; height:40px; border-radius:4px; object-fit:cover;">
                    ${item.name}
                </td>
                <td>₱${item.price.toFixed(2)}</td>
                <td>${item.qty}</td>
                <td>₱${subtotal.toFixed(2)}</td>
                <td><button onclick="removeFromCart(${index})">Remove</button></td>
            </tr>
        `;
    });

    document.getElementById('total').innerText = grandTotal.toLocaleString();
}   

function removeFromCart(index) {
    const item = cart[index];
    products[item.originalIndex].stock += item.qty;
    cart.splice(index, 1);
    updateCartUI();
    updateProductUI();
}

function clearCart() {
    cart.forEach(item => products[item.originalIndex].stock += item.qty);
    cart = [];
    updateCartUI();
    updateProductUI();
}

function printReceipt() {
    if (cart.length === 0) return;
    
    const totalAmount = parseFloat(document.getElementById('total').innerText.replace(/,/g, ''));
    dailyTotal += totalAmount;
    
    localStorage.setItem('dailyTotal', dailyTotal);
    localStorage.setItem('gymProducts', JSON.stringify(products));
    
    alert(`Transaction Successful!\nTotal: ₱${totalAmount.toFixed(2)}`);
    
    cart = [];
    updateCartUI();
    updateDailyTotalUI();
}

function updateDailyTotalUI() {
    document.getElementById('dailyTotal').innerText = dailyTotal.toLocaleString();
}