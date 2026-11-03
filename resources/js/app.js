import Alpine from 'alpinejs';
import counterOrder from './admin/counter-order';
import productForm from './admin/product-form';
import stockReceipt from './admin/stock-receipt';
import orderPage from './shop/order-page';

Alpine.data('productForm', productForm);
Alpine.data('stockReceipt', stockReceipt);
Alpine.data('counterOrder', counterOrder);
Alpine.data('orderPage', orderPage);

window.Alpine = Alpine;
Alpine.start();
