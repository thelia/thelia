import '../css/app.css';
import * as utilsBack from '@thelia/utils-back';
import { SideBar } from '@components/SideBar/SideBar';
import { Tabs } from '@components/Tabs/Tabs';
import { Modal } from '@components/Modal/Modal';
import { TextAreaEditor } from '@components/TextAreaEditor/TextAreaEditor';
import { BackToTop } from '@components/BackToTop/BackToTop';

SideBar();
Tabs();
Modal();
TextAreaEditor();
BackToTop();

window.TheliaJS = utilsBack;