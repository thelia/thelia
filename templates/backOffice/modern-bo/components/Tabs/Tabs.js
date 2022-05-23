export const Tabs = () => {

   let tabs = document.querySelectorAll(".Tabs");

   if(!tabs) return;

    function removeActive(tabsHeader, tabContent){
        for(let i = 0; i < tabsHeader.length; i++){
            tabsHeader[i].classList.remove("active");
            tabContent[i].classList.remove("active");
        }
    }

    function tabsKeyboard(tabsHeader, tabContent){
        
        document.body.addEventListener("keydown", function(event){
            if(event.key === 'Tab') {
                for(let i = 0; i < tabsHeader.length; i++){
                    tabsHeader[i].classList.remove("active");
                    tabContent[i].classList.remove("active");
                }
            }
        })
    }

    function ActiveTab(event, tabsHeader, tabContent, i){
            event.preventDefault();
            removeActive(tabsHeader, tabContent);
            tabsHeader[i].classList.add("active");
            tabContent[i].classList.add("active");
    }

   for(let i = 0; i < tabs.length; i++){
       let tabsHeader = tabs[i].querySelectorAll(".TabHeader a");
       let tabContent = tabs[i].querySelectorAll(".Item");
       tabsKeyboard();
       for(let i = 0; i < tabsHeader.length; i++){
           tabsHeader[i].addEventListener("click", e => {
               ActiveTab(e, tabsHeader, tabContent, i);
        })
        tabsHeader[i].addEventListener("focus", e => {
            ActiveTab(e, tabsHeader, tabContent, i);
     })
    }
    }
}
    




