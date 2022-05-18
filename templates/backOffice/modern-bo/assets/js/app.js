import '../css/app.css';
import * as utilsBack from '@thelia/utils-back';
import { SideBar } from '@components/SideBar/SideBar';
import { TextAreaEditor } from '@components/TextAreaEditor/TextAreaEditor';
import { Tables } from '@components/Tables/Tables';

SideBar();
TextAreaEditor();
Tables();

window.TheliaJS = utilsBack;