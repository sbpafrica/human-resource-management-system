
declare module 'react-to-pdf' {
    import { ReactNode, RefObject } from 'react';

    interface PdfProps {
        targetRef: RefObject<HTMLElement>;
        filename?: string;
        options?: any;
        x?: number;
        y?: number;
        scale?: number;
        children: (props: { toPdf: () => void }) => ReactNode;
    }

    const Pdf: React.FC<PdfProps>;
    export default Pdf;
}
