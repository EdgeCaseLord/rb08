import recipeManager from './recipe-manager';
Alpine.data('recipeManager', recipeManager);

// document.addEventListener('DOMContentLoaded', () => {
//     const settingsLink = document.querySelector('a[href="#"].filament-user-menu-item-settings');
//     if (settingsLink) {
//         settingsLink.addEventListener('click', (e) => {
//             e.preventDefault();
//             window.Livewire.dispatch('open-modal', { id: 'settings-modal' });
//         });
//     }
// });
