import type { route as routeFn } from 'ziggy-js';

declare global {
  const route: typeof routeFn;
  interface Window {
    pdfMake: {
      vfs: { [file: string]: string };
      fonts: {
        [key: string]: {
          normal: string;
          bold: string;
          italics: string;
          bolditalics: string;
        };
      };
      createPdf: (documentDefinitions: any) => {
        download: (filename: string) => void;
      };
    };
  }
}

declare module 'pdfmake/build/vfs_fonts' {
  const vfsFonts: { [file: string]: string };
  export default vfsFonts;
}

interface TDocumentDefinitions {
  content: Array<any>;
  styles?: Record<string, any>;
  pageMargins?: [number, number, number, number];
  defaultStyle?: { font?: string; fontSize?: number };
}
