/* import Mmenu from ''

new Mmenu("#sidebar", {
  "extensions": [
     "theme-dark"
  ],
  "navbars": [
     {
        "position": "top",
        "content": [
           "searchfield"
        ]
     }
  ]
}); */

export const SideBar = () => {
   const orderSubmenu = document.querySelector('.sidebar-order-submenu')
   const orderDropdownButton = document.querySelector('[data-toggle-order-dropdown]')
   const orderDropdownClose = document.querySelector('[data-close-order-dropdown]')

   orderDropdownButton.addEventListener('click', () => {
      orderSubmenu.classList.toggle('hidden')
      document.querySelector('#main').classList.toggle('lg:filter')
      document.querySelector('#main').classList.toggle('lg:blur-sm')
   })

   orderDropdownClose.addEventListener('click', () => {
      orderSubmenu.classList.add('hidden')
      document.querySelector('#main').classList.remove('lg:filter')
      document.querySelector('#main').classList.remove('lg:blur-sm')

   })
}
