import Alpine from 'alpinejs';
import productForm from './admin/product-form';

Alpine.data('productForm', productForm);

window.Alpine = Alpine;
Alpine.start();
