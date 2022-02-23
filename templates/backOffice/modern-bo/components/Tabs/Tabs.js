


export const Tabs = () => {
    
    let tabs = document.querySelectorAll(".TabHeader a");
    // let content = document.querySelectorAll(".TabsContent");

    for(let i = 0 ; i < tabs.length; i++){
        tabs[i].addEventListener('click', () => click(i));
     }

        function removeActive() {
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
                // content[i].classList.remove('active');
            }
        }

        function click(currentTab) {
            removeActive();
            tabs[currentTab].classList.add('active');
            // content[currentTab].classList.add('active');
            console.log(currentTab);
        }

       
    }
    



