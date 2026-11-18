import { i18n } from '../../i18n';
import { COPY } from './copy-data';

export const lt = (key) => COPY[i18n.locale]?.[key] ?? COPY.fa[key] ?? key;
