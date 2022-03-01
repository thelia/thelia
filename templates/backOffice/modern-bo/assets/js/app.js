import '../css/app.css';
import * as utilsBack from '@thelia/utils-back';
import { SideBar } from '@components/SideBar/SideBar';
import { Tabs } from '@components/Tabs/Tabs';

SideBar();
Tabs();

window.TheliaJS = utilsBack;