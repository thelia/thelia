import MmenuLight from 'mmenu-light'

export const SideBar = () => {
  const sidebar = document.querySelector('aside')
  const burger = document.querySelector('a[href="#Sidebar"]')

  if (!sidebar || !burger) return;

  const menu = new MmenuLight(sidebar, "(max-width: 1024px)")

  const navigator = menu.navigation({
    theme: "dark",
    title: "Thelia"
  })
  const drawer = menu.offcanvas({})

  burger.addEventListener('click', e => {
    e.preventDefault()

    burger.classList.contains('is-open') ? drawer.close() : drawer.open()
  })

  return navigator
}