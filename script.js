// Nigerian Online Marketplace - Main JavaScript
// Interactive features and AJAX functionality

// Theme Management
class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    
    init() {
        this.applyTheme(this.currentTheme);
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleTheme());
        }
    }
    
    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.currentTheme);
        this.applyTheme(this.currentTheme);
    }
    
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.textContent = theme === 'light' ? '🌙' : '☀️';
        }
    }
}

// Mobile Menu
class MobileMenu {
    constructor() {
        this.menuBtn = document.getElementById('mobile-menu-btn');
        this.nav = document.querySelector('nav');
        this.init();
    }
    
    init() {
        if (this.menuBtn) {
            this.menuBtn.addEventListener('click', () => this.toggleMenu());
        }
    }
    
    toggleMenu() {
        this.nav.classList.toggle('active');
    }
}

// Product Manager
class ProductManager {
    constructor() {
        this.products = [];
        this.currentPage = 1;
        this.filters = {};
        this.init();
    }
    
    init() {
        this.loadProducts();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        const searchForm = document.getElementById('search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => this.handleSearch(e));
        }
        
        const filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => this.handleFilter(e));
        }
    }
    
    async loadProducts(page = 1, filters = {}) {
        try {
            const params = new URLSearchParams({
                action: 'list',
                page: page,
                ...filters
            });
            
            const response = await fetch(`products.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.products = data.products;
                this.currentPage = data.current_page;
                this.renderProducts();
                this.renderPagination(data.pages, data.current_page);
            }
        } catch (error) {
            console.error('Error loading products:', error);
            this.showError('Failed to load products');
        }
    }
    
    handleSearch(e) {
        e.preventDefault();
        const searchQuery = document.getElementById('search-input')?.value;
        this.filters.search = searchQuery;
        this.currentPage = 1;
        this.loadProducts(this.currentPage, this.filters);
    }
    
    handleFilter(e) {
        e.preventDefault();
        this.filters = {
            category_id: document.getElementById('category-filter')?.value,
            min_price: document.getElementById('min-price')?.value,
            max_price: document.getElementById('max-price')?.value,
            location: document.getElementById('location-filter')?.value,
            sort: document.getElementById('sort-filter')?.value
        };
        this.currentPage = 1;
        this.loadProducts(this.currentPage, this.filters);
    }
    
    renderProducts() {
        const container = document.getElementById('products-grid');
        if (!container) return;
        
        if (this.products.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>No products found</h3>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.products.map(product => `
            <div class="product-card" data-product-id="${product.id}">
                <img src="${product.images?.[0] || 'placeholder.jpg'}" alt="${product.title}" class="product-image">
                <div class="product-info">
                    <h3 class="product-title">${this.escapeHtml(product.title)}</h3>
                    <p class="product-price">₦${this.formatPrice(product.price)}</p>
                    <p class="product-location">📍 ${this.escapeHtml(product.location)}</p>
                    <div class="product-meta">
                        <span>⭐ ${product.seller_rating?.toFixed(1) || 'New'}</span>
                        <span>👁️ ${product.views}</span>
                    </div>
                    <div class="product-actions">
                        <button class="btn btn-primary" onclick="app.viewProduct(${product.id})">View</button>
                        <button class="btn btn-outline" onclick="app.addToCart(${product.id})">Add to Cart</button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    renderPagination(totalPages, currentPage) {
        const container = document.getElementById('pagination');
        if (!container || totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="pagination">';
        
        if (currentPage > 1) {
            html += `<a href="#" onclick="app.changePage(${currentPage - 1})">Previous</a>`;
        }
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<span class="current">${i}</span>`;
            } else {
                html += `<a href="#" onclick="app.changePage(${i})">${i}</a>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<a href="#" onclick="app.changePage(${currentPage + 1})">Next</a>`;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    changePage(page) {
        this.currentPage = page;
        this.loadProducts(page, this.filters);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    async viewProduct(productId) {
        try {
            const response = await fetch(`products.php?action=detail&id=${productId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showProductModal(data.product);
            }
        } catch (error) {
            console.error('Error loading product:', error);
        }
    }
    
    showProductModal(product) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                <div class="product-detail">
                    <div class="product-gallery">
                        ${product.images?.map(img => `<img src="${img}" alt="${product.title}">`).join('') || '<img src="placeholder.jpg">'}
                    </div>
                    <div class="product-details">
                        <h2>${this.escapeHtml(product.title)}</h2>
                        <p class="product-price">₦${this.formatPrice(product.price)}</p>
                        <p class="product-location">📍 ${this.escapeHtml(product.location)}</p>
                        <p class="product-description">${this.escapeHtml(product.description)}</p>
                        <div class="seller-info">
                            <h3>Seller: ${this.escapeHtml(product.username)}</h3>
                            <p>⭐ Rating: ${product.seller_rating?.toFixed(1) || 'New'}</p>
                            <p>📱 ${this.escapeHtml(product.phone)}</p>
                        </div>
                        <div class="product-actions">
                            <button class="btn btn-primary" onclick="app.addToCart(${product.id})">Add to Cart</button>
                            <button class="btn btn-outline" onclick="app.openChat(${product.seller_id}, ${product.id})">Chat with Seller</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('en-NG').format(price);
    }
    
    showError(message) {
        const container = document.getElementById('products-grid');
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
}

// Cart Manager
class CartManager {
    constructor() {
        this.cart = [];
        this.init();
    }
    
    init() {
        this.loadCart();
        this.setupEventListeners();
        this.updateCartBadge();
    }
    
    setupEventListeners() {
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            cartIcon.addEventListener('click', () => this.showCart());
        }
    }
    
    async loadCart() {
        try {
            const response = await fetch('cart.php?action=get');
            const data = await response.json();
            
            if (data.success) {
                this.cart = data.cart.items;
                this.updateCartBadge();
            }
        } catch (error) {
            console.error('Error loading cart:', error);
        }
    }
    
    async addToCart(productId, quantity = 1) {
        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            formData.append('csrf_token', this.getCSRFToken());
            
            const response = await fetch('cart.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Item added to cart!');
                this.loadCart();
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showNotification('Failed to add item to cart', 'error');
        }
    }
    
    async removeFromCart(cartItemId) {
        try {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('cart_item_id', cartItemId);
            formData.append('csrf_token', this.getCSRFToken());
            
            const response = await fetch('cart.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadCart();
                this.renderCart();
            }
        } catch (error) {
            console.error('Error removing from cart:', error);
        }
    }
    
    async updateCartItem(cartItemId, quantity) {
        try {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('cart_item_id', cartItemId);
            formData.append('quantity', quantity);
            formData.append('csrf_token', this.getCSRFToken());
            
            const response = await fetch('cart.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadCart();
                this.renderCart();
            }
        } catch (error) {
            console.error('Error updating cart:', error);
        }
    }
    
    showCart() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content cart-modal">
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                <h2>Shopping Cart</h2>
                <div id="cart-items-container"></div>
                <div class="cart-summary"></div>
            </div>
        `;
        document.body.appendChild(modal);
        this.renderCart();
    }
    
    renderCart() {
        const container = document.getElementById('cart-items-container');
        if (!container) return;
        
        if (this.cart.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>Your cart is empty</h3>
                    <p>Start shopping to add items</p>
                </div>
            `;
            return;
        }
        
        let total = 0;
        container.innerHTML = this.cart.map(item => {
            const subtotal = item.price * item.quantity;
            total += subtotal;
            return `
                <div class="cart-item">
                    <img src="${item.images?.[0] || 'placeholder.jpg'}" alt="${item.title}" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3>${this.escapeHtml(item.title)}</h3>
                        <p>Seller: ${this.escapeHtml(item.seller_name)}</p>
                        <p class="cart-item-price">₦${this.formatPrice(item.price)}</p>
                        <div class="cart-item-actions">
                            <button class="btn btn-secondary" onclick="app.cart.updateCartItem(${item.cart_item_id}, ${item.quantity - 1})">-</button>
                            <span>${item.quantity}</span>
                            <button class="btn btn-secondary" onclick="app.cart.updateCartItem(${item.cart_item_id}, ${item.quantity + 1})">+</button>
                            <button class="btn btn-danger" onclick="app.cart.removeFromCart(${item.cart_item_id})">Remove</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        const summaryContainer = document.querySelector('.cart-modal .cart-summary');
        if (summaryContainer) {
            summaryContainer.innerHTML = `
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>₦${this.formatPrice(total)}</span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span>₦${this.formatPrice(total)}</span>
                </div>
                <button class="btn btn-primary" style="width: 100%; margin-top: 20px;" onclick="app.checkout()">Proceed to Checkout</button>
            `;
        }
    }
    
    updateCartBadge() {
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            const count = this.cart.reduce((sum, item) => sum + item.quantity, 0);
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('en-NG').format(price);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
    
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
}

// Messages/Chat Manager
class MessageManager {
    constructor() {
        this.currentConversation = null;
        this.conversations = [];
        this.init();
    }
    
    init() {
        if (document.getElementById('messages-page')) {
            this.loadConversations();
            this.setupEventListeners();
        }
    }
    
    setupEventListeners() {
        const chatForm = document.getElementById('chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', (e) => this.handleSendMessage(e));
        }
    }
    
    async loadConversations() {
        try {
            const response = await fetch('messages.php?action=conversations');
            const data = await response.json();
            
            if (data.success) {
                this.conversations = data.conversations;
                this.renderConversations();
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
        }
    }
    
    async loadConversation(userId, productId = null) {
        try {
            const params = new URLSearchParams({
                action: 'conversation',
                user_id: userId
            });
            
            if (productId) {
                params.append('product_id', productId);
            }
            
            const response = await fetch(`messages.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.currentConversation = userId;
                this.renderMessages(data.messages);
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Error loading conversation:', error);
        }
    }
    
    renderConversations() {
        const container = document.getElementById('conversations-list');
        if (!container) return;
        
        if (this.conversations.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>No conversations yet</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.conversations.map(conv => `
            <div class="conversation-item ${this.currentConversation === conv.other_user_id ? 'active' : ''}" 
                 onclick="app.messages.loadConversation(${conv.other_user_id}, ${conv.last_product_id || 'null'})">
                <h4>${this.escapeHtml(conv.username)}</h4>
                <p>${conv.last_product_title ? this.escapeHtml(conv.last_product_title) + ' - ' : ''}Last message...</p>
                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
            </div>
        `).join('');
    }
    
    renderMessages(messages) {
        const container = document.getElementById('chat-messages');
        if (!container) return;
        
        if (messages.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = messages.map(msg => {
            const isSent = msg.sender_id === this.getCurrentUserId();
            return `
                <div class="message ${isSent ? 'sent' : 'received'}">
                    <div class="message-content">
                        <p>${this.escapeHtml(msg.message)}</p>
                        <span class="message-time">${this.formatTime(msg.created_at)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    async handleSendMessage(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('message-input');
        const message = messageInput.value.trim();
        
        if (!message || !this.currentConversation) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'send');
            formData.append('receiver_id', this.currentConversation);
            formData.append('message', message);
            formData.append('csrf_token', this.getCSRFToken());
            
            const response = await fetch('messages.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                messageInput.value = '';
                this.loadConversation(this.currentConversation);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    openChat(userId, productId = null) {
        this.currentConversation = userId;
        this.loadConversation(userId, productId);
    }
    
    scrollToBottom() {
        const container = document.getElementById('chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
    
    getCurrentUserId() {
        return document.querySelector('meta[name="user-id"]')?.content || 0;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
}

// Main Application
class App {
    constructor() {
        this.theme = new ThemeManager();
        this.mobileMenu = new MobileMenu();
        this.products = new ProductManager();
        this.cart = new CartManager();
        this.messages = new MessageManager();
    }
    
    // Product methods
    viewProduct(productId) {
        this.products.viewProduct(productId);
    }
    
    // Cart methods
    addToCart(productId) {
        this.cart.addToCart(productId);
    }
    
    checkout() {
        window.location.href = 'cart.html';
    }
    
    // Messages methods
    openChat(userId, productId) {
        this.messages.openChat(userId, productId);
    }
    
    // Page navigation
    changePage(page) {
        this.products.changePage(page);
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new App();
});