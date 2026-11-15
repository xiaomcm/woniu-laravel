#!/usr/bin/env bash
# 本地模拟服务器（开发用）
#
#   bash tools/local_server.sh start    # 启动
#   bash tools/local_server.sh stop     # 停止
#   bash tools/local_server.sh restart  # 重启
#   bash tools/local_server.sh status   # 查看状态
#
# 关键点：必须把 server.php 作为路由脚本传给 php -S，
# 否则带扩展名的路径（/covers/xxx.jpg、/blank/1200x900.jpg、/uploads/...）
# 会被 PHP 内置服务器当成静态文件直接 404，根本进不到 Laravel。
#
# 本脚本只服务当前项目，默认端口 8102，可用 PORT=xxxx 覆盖。
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${PORT:-8102}"
HOST="${HOST:-127.0.0.1}"
PHP_BIN="${PHP_BIN:-/c/Users/杨明/AppData/Local/Temp/php83/php.exe}"
LOG="$ROOT/storage/logs/server.log"
PIDFILE="$ROOT/storage/tmp/local-server.pid"

if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
  echo "端口不合法：$PORT（需要 1024-65535 之间的整数）" >&2
  exit 3
fi

case "${1:-start}" in
  start)
    if curl -s -o /dev/null --noproxy '*' "http://$HOST:$PORT/" 2>/dev/null; then
      echo "已经在跑: http://$HOST:$PORT"
      exit 0
    fi
    mkdir -p "$(dirname "$LOG")" "$(dirname "$PIDFILE")"
    cd "$ROOT" || exit 1
    nohup "$PHP_BIN" -S "$HOST:$PORT" -t public server.php >>"$LOG" 2>&1 &
    echo $! > "$PIDFILE"
    sleep 1.5
    code=$(curl -s -o /dev/null -w "%{http_code}" --noproxy '*' "http://$HOST:$PORT/")
    echo "启动完成: http://$HOST:$PORT/  (HTTP $code, PID $(cat "$PIDFILE"))"
    ;;
  stop)
    if [ -f "$PIDFILE" ]; then
      pid=$(cat "$PIDFILE")
      taskkill //PID "$pid" //F >/dev/null 2>&1 && echo "已停止 PID $pid" || echo "PID $pid 未在运行"
      rm -f "$PIDFILE"
    else
      # 兜底：按端口找进程
      pid=$(netstat -ano | grep -E ":$PORT .*LISTENING" | awk '{print $NF}' | head -1)
      if [ -n "${pid:-}" ]; then
        taskkill //PID "$pid" //F >/dev/null 2>&1 && echo "已停止 PID $pid" || echo "停止失败"
      else
        echo "没有正在运行的实例"
      fi
    fi
    ;;
  restart)
    "$0" stop; sleep 1; "$0" start
    ;;
  status)
    code=$(curl -s -o /dev/null -w "%{http_code}" --noproxy '*' "http://$HOST:$PORT/")
    echo "HTTP $code  http://$HOST:$PORT/"
    ;;
  *)
    echo "用法: bash tools/local_server.sh {start|stop|restart|status}"
    exit 1
    ;;
esac
