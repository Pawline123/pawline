export const dynamic = 'force-dynamic';
export async function GET() {
  try {
    const response = await fetch('https://pawline-shop.de/products.json?limit=250', { cache: 'no-store' });
    if (!response.ok) throw new Error(`Shopify antwortet mit ${response.status}`);
    const data = await response.json();
    return Response.json(data, { headers: { 'Cache-Control': 's-maxage=300, stale-while-revalidate=3600' } });
  } catch (error) {
    return Response.json({ products: [], error: error.message }, { status: 502 });
  }
}
