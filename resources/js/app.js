import Alpine from 'alpinejs';
import productForm from './admin/product-form';
import stockReceipt from './admin/stock-receipt';

Alpine.data('productForm', productForm);
Alpine.data('stockReceipt', stockReceipt);

window.Alpine = Alpine;
Alpine.start();
