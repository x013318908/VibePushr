function getEntrypointPath() {
  const raw = (process.env.VP_ENTRYPOINT || 'vp.php').trim();
  const cleaned = raw.replace(/^\/+/, '');
  return `/${cleaned || 'vp.php'}`;
}

module.exports = {
  getEntrypointPath,
};
