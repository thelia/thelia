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
   // const ordersDropdown = document.querySelector('#orders_menu')
   // const toolsDropdown = document.querySelector('#tools_menu')
   const ordersDropdownClose = document.querySelector('[data-close-orders-dropdown]')
   const toolsDropdownClose = document.querySelector('[data-close-tools-dropdown]')

   ordersDropdownClose.addEventListener('click', (e) => {
      console.log("-", document.activeElement)
      document.body.focus()
      console.log(document.activeElement)

   })

   toolsDropdownClose.addEventListener('click', (e) => {
      document.body.focus()
   })
}
