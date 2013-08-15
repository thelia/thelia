<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Event\ImageEvent;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Core\Event\TheliaEvents;

/**
 * The image loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Image extends BaseLoop
{
    /**
     * @var array Possible image sources
     */
    protected $possible_sources = array('category', 'product', 'folder', 'content');

    /**
     * Dynamically create the search query, and set the proper filter and order
     *
     * @return ModelCriteria the propel Query object
     */
    protected function getSearchQuery() {

        $search = null;

        foreach($this->possible_sources as $source) {

            $argValue = intval($this->getArgValue($source));

            if ($argValue > 0) {

                $object = ucfirst($source);

                $queryClass   = sprintf("%sImageQuery", $object);
                $filterMethod = sprintf("filterBy%s", $object);
                $mapClass     = sprintf("\Thelia\Model\Map\%sI18nTableMap", $object);

                // xxxImageQuery::create()
                $method = new \ReflectionMethod($queryClass, 'create');
                $search = $reflectionMethod->invoke(null); // Static !

                // $query->filterByXXX($id)
                $method = new \ReflectionMethod($queryClass, $filterMethod);
                $method->invoke($search, $argValue);

                $map = new \ReflectionClass($mapClass);
                $title_map = $map->getConstant('TITLE');

                $orders  = $this->getOrder();

                // Results ordering
                foreach ($orders as $order) {
                    switch ($order) {
                        case "alpha":
                            $search->addAscendingOrderByColumn($title_map);
                            break;
                        case "alpha-reverse":
                            $search->addDescendingOrderByColumn($title_map);
                            break;
                        case "manual-reverse":
                            $search->orderByPosition(Criteria::DESC);
                            break;
                        case "manual":
                            $search->orderByPosition(Criteria::ASC);
                            break;
                        case "random":
                            $search->clearOrderByColumns();
                            $search->addAscendingOrderByColumn('RAND()');
                            break(2);
                            break;
                    }
                }

                break;
            }
        }

        if ($search == null)
            throw new \InvalidArgumentException(sprintf("Unable to find image source. Valid sources are %s", implode(',', $this->possible_sources)));

        return $search;
    }

    /**
     * @param unknown $pagination
     */
    public function exec($pagination)
    {
        // Select the proper query to use
        $search = $this->getSearchQuery();

        $id = $this->getId();

        if (! is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (!is_null($exclude))
            $search->filterById($exclude, Criteria::NOT_IN);

        // Create image processing event
        $event = new ImageEvent($this->request);

        // Prepare tranformations
        // Setup required transformations
        $width = $this->getWidth();
        $height = $this->getHeight();
        $resize_mode = $this->getResizeMode();
        $rotation = $this->getRotation();
        $background_color = $this->getBackgroundColor();
        $quality = $this->getQuality();
        $effects = $this->getEffects();
        $effects = $this->getEffects();
        if (! is_null($effects)) {
            $effects = explode(',', $effects);
        }

        /**
         * \Criteria::INNER_JOIN in second parameter for joinWithI18n  exclude query without translation.
         *
         * @todo : verify here if we want results for row without translations.
         */

        $search->joinWithI18n(
                $this->request->getSession()->getLocale(),
                (ConfigQuery::read("default_lang_without_translation", 1)) ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN
        );

        $results = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($results as $result) {

            // Create image processing event
            $event = new ImageEvent($this->request);

            // Setup required transformations
            if (! is_null($width)) $event->setWidth($width);
            if (! is_null($height)) $event->setHeigth($height);
            if (! is_null($resize_mode)) $event->setResizeMode($resize_mode);
            if (! is_null($rotation)) $event->setRotation($rotation);
            if (! is_null($background_color)) $event->setBackgroundColor($background_color);
            if (! is_null($quality)) $event->setQuality($quality);
            if (! is_null($effects)) $event->setEffects($effects);

            // Dispatch image processing event
            $this->dispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $event);

            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("ID", $result->getId())
                ->set("IMAGE_URL", $event->getFileUrl())
                ->set("FILE_URL", $event->
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
/*
#PRODUIT : identifiant du produit associé (valué si le paramètre "produit" a été indiqué)
#PRODTITRE : titre du produit associé (valué si le paramètre "produit" a été indiqué)
#PRODREF : référence du produit associé (valué si le paramètre "produit" a été indiqué)
#RUBRIQUE : identifiant de la rubrique associée (valué si le paramètre "rubrique" a été indiqué)
#RUBTITRE : titre de la rubrique associée (valué si le paramètre "rubrique" a été indiqué)
#DOSSIER : identifiant du dossier associée (valué si le paramètre "dossier" a été indiqué)
#DOSTITRE : titre du dossier associée (valué si le paramètre "dossier" a été indiqué)
#CONTENU : identifiant du contenu associée (valué si le paramètre "contenu" a été indiqué)
#CONTTITRE : titre du contenu associée (valué si le paramètre "contenu" a été indiqué)
#IMAGE : URL de l'image transformée (redimensionnée, inversée, etc. suivant les paramètres d'entrée de la boucle).
#FICHIER : URL de l'image originale
#ID : identifiant de l'image
#TITRE : titre de l'image
#CHAPO : description courte de l'image
#DESCRIPTION : description longue de l'image
#COMPT : compteur débutant à 1. Utile pour l'utilisation de Javascript.
 */
    }

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $collection = new ArgumentCollection(

            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                    'order',
                    new TypeCollection(
                            new Type\EnumListType(array('alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'))
                    ),
                    'manual'
            ),

            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                        new Type\EnumListType(array('crop', 'borders', 'none'))
                ),
                'none'
            ),
            Argument::createAnyTypeArgument('effects'),

            Argument::createIntTypeArgument('category'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntTypeArgument('folder'),
            Argument::createIntTypeArgument('content'),

            new Argument(
                'source',
                new TypeCollection(
                        new Type\EnumListType($this->possible_sources)
                )
            ),

            Argument::createIntListTypeArgument('lang')
        );

        // Add possible image sources
        foreach($possible_sources as $source) {
            $collection->addArgument(Argument::createIntTypeArgument($source))
        }

        return $collection;
    }
}