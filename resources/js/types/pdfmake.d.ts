// resources/js/types/pdfmake.d.ts
declare module 'pdfmake' {
  interface PdfMake {
    vfs: any;
  }
  const pdfMake: PdfMake;
  export = pdfMake;
}

declare module 'pdfmake/build/vfs_fonts' {
  interface VfsFonts {
    pdfMake: {
      vfs: any;
    };
  }
  const vfsFonts: VfsFonts;
  export = vfsFonts;
}

declare global {
  interface Window {
    pdfMake: {
      vfs: any;
    };
  }
}
