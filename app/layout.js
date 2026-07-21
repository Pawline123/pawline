import './styles.css';
export const metadata = { title: 'Pawline – Für beste Freunde', description: 'Hochwertige Produkte für Hunde und Katzen.' };
export default function RootLayout({ children }) {
  return <html lang="de"><body>{children}</body></html>;
}
