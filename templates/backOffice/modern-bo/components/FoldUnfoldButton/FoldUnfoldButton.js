import unfoldImg from '../../assets/images/svg/unfold-arrow.svg';

export const FoldUnfoldButton = () => {

  document.addEventListener('DOMContentLoaded', function() {

      const btn = document.getElementById('btn');
      let img = btn.querySelector('img');
      const textBtn = document.getElementById('textBtn');

      const pathFoldImg = img.src;

      btn.onclick = function() {

        if (img.classList.contains('fold-arrow')){
          textBtn.innerText = "DÉPLIER"
          img.src = unfoldImg;
          img.classList.toggle('fold-arrow');
        } else {
          textBtn.innerText = "REPLIER"
          img.src = pathFoldImg;
          img.classList.toggle('fold-arrow');
        }
      }

  });

}
