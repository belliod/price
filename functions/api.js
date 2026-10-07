// Cloudflare Pages Function：数据读写接口（替代群晖的 api.php）
// 用法：把本仓库连接到 Cloudflare Pages 后，页面会自动探测 /api 并使用本接口，
//       数据存入 Cloudflare KV（云端持久化，不依赖 PHP）。
// 配置：在 Pages 项目 Settings → Functions → KV namespace bindings 中，
//       将变量名设为 STORES_KV，绑定你创建的 KV 命名空间。
export async function onRequest(context) {
  const { request, env } = context;
  const url = new URL(request.url);
  const action = url.searchParams.get('action') || 'load';
  const kv = env.STORES_KV;
  const json = (data, status) => new Response(JSON.stringify(data), {
    status: status || 200,
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' }
  });

  if (action === 'load') {
    const data = kv ? await kv.get('stores', 'json') : null;
    return json(data || []);
  }

  if (action === 'save' && request.method === 'POST') {
    const raw = await request.text();
    if (!raw || raw.length > 2 * 1024 * 1024) return json({ ok: false, error: 'too_large' }, 413);
    let arr;
    try { arr = JSON.parse(raw); } catch (e) { return json({ ok: false, error: 'not_array' }, 400); }
    if (!Array.isArray(arr)) return json({ ok: false, error: 'not_array' }, 400);
    if (!kv) return json({ ok: false, error: 'no_kv', msg: '未绑定 STORES_KV 命名空间：请在 Pages 项目 Settings → Functions → KV namespace bindings 配置' }, 500);
    await kv.put('stores', raw);
    return json({ ok: true });
  }

  if (action === 'diag') {
    const exists = kv ? !!(await kv.get('stores')) : false;
    return json({ ok: true, php: 'cloudflare-pages-functions', dirWritable: !!kv, storesExists: exists });
  }

  return json({ ok: false, error: 'unknown_action' }, 404);
}