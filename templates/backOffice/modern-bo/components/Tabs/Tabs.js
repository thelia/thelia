export const Tabs = () => {

    let tab = document.querySelectorAll(".Tabs");
    console.log(tab);

    let tabs = tab[0].querySelectorAll(".TabHeader a");
    let content = tab[0].querySelectorAll(".Item");

    for(let i = 0 ; i < tab.length; i++){
        console.log(tab)
     }

    for(let i = 0 ; i < tabs.length; i++){
        tabs[i].addEventListener('click', () => click(i));
     }

        function removeActive() {
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
                content[i].classList.remove('active');
                content[i].classList.remove('block');
            }
        }

        function click(currentTab) {
            removeActive();
            tabs[currentTab].classList.add('active');
            content[currentTab].classList.add('active');
            content[currentTab].classList.add('hidden');
            content[currentTab].classList.remove('block');
        }
    }
    




