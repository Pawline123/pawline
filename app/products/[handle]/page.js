import ProductPage from '../../../components/ProductPage';
export default async function Page({ params }) { const { handle } = await params; return <ProductPage handle={handle} />; }
