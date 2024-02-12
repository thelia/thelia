import Selection from '@components/smarty/Selection/Selection';
import StratSeo from '@components/smarty/StratSeo/StratSeo';
import CategoriesPanels from '../../../components/smarty/CategoriesPanels/CategoriesPanels';

Selection();
CategoriesPanels();
StratSeo();
improveAccesibilityOfSkipContent();

// Skip content for home
function improveAccesibilityOfSkipContent() {
  const skipToContent = document.querySelector('.sr-only[href="#content"]');
  const content = document.getElementById('content');

  if (!skipToContent || !content) return;

  skipToContent.addEventListener('click', () => {
    content.tabIndex = 1;
  });

  content.addEventListener('focusout', () => (content.tabIndex = -1));
}
