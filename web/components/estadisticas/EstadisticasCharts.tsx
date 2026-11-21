"use client";

import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  LabelList,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type {
  EstadisticaPorAnio,
  EstadisticaPorEstado,
  EstadisticaPorTipo,
  EstadisticaPorUnidad,
} from "@/types/estadistica";

const colors = {
  brand: "#D13B3B",
  brandDark: "#B52F35",
  orange: "#B96832",
  yellow: "#B08A2E",
  slate: "#65748B",
  sidebar: "#354052",
  muted: "#858D9A",
};

const statusColors: Record<string, string> = {
  Borrador: colors.slate,
  EnCorreccion: colors.yellow,
  "Revisión": colors.orange,
  Aprobado: colors.sidebar,
  Firmado: colors.brand,
  Rescindido: colors.muted,
  Rechazado: colors.brandDark,
  Archivado: "#737C89",
};

const statusOrder = [
  "Borrador",
  "EnCorreccion",
  "Revisión",
  "Aprobado",
  "Firmado",
  "Rescindido",
  "Rechazado",
  "Archivado",
];

const typeColors: Record<string, string> = {
  "Colaboración": colors.brand,
  "Pasantía": colors.orange,
};

const tooltipStyle = {
  backgroundColor: "var(--surface)",
  border: "1px solid var(--ui-border)",
  borderRadius: "0.5rem",
  color: "var(--foreground)",
};

const axisTick = {
  fill: "var(--foreground)",
  fontSize: 12,
};

function formatNumber(value: number): string {
  return new Intl.NumberFormat("es-AR").format(value);
}

function shortenLabel(value: string, limit = 22): string {
  return value.length > limit ? `${value.slice(0, limit - 1)}…` : value;
}

function ChartPanel({
  title,
  description,
  hasData,
  children,
}: {
  title: string;
  description: string;
  hasData: boolean;
  children: React.ReactNode;
}) {
  return (
    <section className="min-w-0 overflow-hidden rounded-lg border border-ui-border bg-surface p-4 text-foreground shadow-sm sm:p-5">
      <h2 className="text-lg font-bold">{title}</h2>
      <p className="mb-4 mt-1 text-sm text-foreground/70">{description}</p>
      {hasData ? (
        children
      ) : (
        <p className="rounded-md bg-page p-4 text-sm">
          No hay datos para mostrar con estos filtros.
        </p>
      )}
    </section>
  );
}

export function ConveniosPorEstadoChart({
  data,
}: {
  data: EstadisticaPorEstado[];
}) {
  const order = new Map(statusOrder.map((state, index) => [state, index]));
  const sortedData = data.slice().sort((a, b) => {
    const orderA = order.get(a.estado) ?? Number.MAX_SAFE_INTEGER;
    const orderB = order.get(b.estado) ?? Number.MAX_SAFE_INTEGER;

    return orderA === orderB
      ? a.estado.localeCompare(b.estado, "es")
      : orderA - orderB;
  });
  const chartHeight = Math.max(260, sortedData.length * 38 + 72);

  return (
    <ChartPanel
      title="Convenios por estado"
      description="Cantidad por estado visible, ordenada según el circuito del convenio."
      hasData={sortedData.length > 0}
    >
      <div
        className="max-h-[560px] overflow-y-auto"
        role="group"
        aria-label="Gráfico de barras horizontales de convenios por estado"
      >
        <ResponsiveContainer width="100%" height={chartHeight} minWidth={0}>
          <BarChart
            data={sortedData}
            layout="vertical"
            margin={{ top: 8, right: 32, bottom: 8, left: 0 }}
          >
            <CartesianGrid
              horizontal={false}
              stroke="var(--ui-border)"
              strokeDasharray="3 3"
            />
            <XAxis
              type="number"
              allowDecimals={false}
              tick={axisTick}
              tickFormatter={formatNumber}
              axisLine={{ stroke: "var(--ui-border)" }}
              tickLine={false}
            />
            <YAxis
              type="category"
              dataKey="estado"
              width={128}
              interval={0}
              tick={axisTick}
              tickFormatter={(value: string) => shortenLabel(value, 18)}
              axisLine={false}
              tickLine={false}
            />
            <Tooltip
              contentStyle={tooltipStyle}
              labelStyle={{ color: "var(--foreground)", fontWeight: 700 }}
              formatter={(value) => [
                `${formatNumber(Number(value))} convenios`,
                "Cantidad",
              ]}
              labelFormatter={(label) => String(label)}
            />
            <Bar
              dataKey="total"
              name="Convenios"
              radius={[0, 4, 4, 0]}
              maxBarSize={24}
            >
              {sortedData.map((item) => (
                <Cell
                  key={item.estado}
                  fill={statusColors[item.estado] ?? colors.sidebar}
                />
              ))}
              <LabelList
                dataKey="total"
                position="right"
                fill="var(--foreground)"
              />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartPanel>
  );
}

export function ConveniosPorTipoChart({
  data,
}: {
  data: EstadisticaPorTipo[];
}) {
  const total = data.reduce((sum, item) => sum + item.total, 0);
  const chartData = data.map((item) => ({
    name: item.tipo,
    value: item.total,
  }));
  const hasData = chartData.length > 0 && total > 0;

  return (
    <ChartPanel
      title="Convenios por tipo"
      description="Distribución por tipo de instrumento. El detalle muestra cantidad y porcentaje."
      hasData={hasData}
    >
      <div
        role="group"
        aria-label="Gráfico de dona de convenios por tipo"
        className="h-[320px] min-w-0"
      >
        <ResponsiveContainer width="100%" height="100%" minWidth={0}>
          <PieChart>
            <Pie
              data={chartData}
              dataKey="value"
              nameKey="name"
              innerRadius="58%"
              outerRadius="80%"
              paddingAngle={3}
              stroke="var(--surface)"
              strokeWidth={3}
              isAnimationActive={false}
            >
              {chartData.map((item, index) => (
                <Cell
                  key={item.name}
                  fill={
                    typeColors[item.name] ??
                    [colors.yellow, colors.slate][index % 2]
                  }
                />
              ))}
            </Pie>
            <Tooltip
              contentStyle={tooltipStyle}
              labelStyle={{ color: "var(--foreground)", fontWeight: 700 }}
              formatter={(value) => {
                const count = Number(value);
                const percentage = (count / total) * 100;

                return [
                  `${formatNumber(count)} (${new Intl.NumberFormat("es-AR", {
                    maximumFractionDigits: 1,
                  }).format(percentage)}%)`,
                  "Convenios",
                ];
              }}
            />
            <Legend
              verticalAlign="bottom"
              iconType="circle"
              wrapperStyle={{ color: "var(--foreground)", fontSize: 13 }}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </ChartPanel>
  );
}

export function FirmasPorAnioChart({
  data,
}: {
  data: EstadisticaPorAnio[];
}) {
  const sortedData = data.slice().sort((a, b) => a.anio - b.anio);

  return (
    <ChartPanel
      title="Evolución anual de firmas"
      description="Cantidad de convenios firmados en cada año disponible."
      hasData={sortedData.length > 0}
    >
      <div
        role="img"
        aria-label="Gráfico de barras verticales de firmas por año"
        className="h-[320px] min-w-0"
      >
        <ResponsiveContainer width="100%" height="100%" minWidth={0}>
          <BarChart
            data={sortedData}
            margin={{ top: 24, right: 12, bottom: 8, left: 0 }}
          >
            <CartesianGrid
              vertical={false}
              stroke="var(--ui-border)"
              strokeDasharray="3 3"
            />
            <XAxis
              dataKey="anio"
              type="category"
              interval={0}
              tick={axisTick}
              tickFormatter={(value: string | number) => String(value)}
              axisLine={{ stroke: "var(--ui-border)" }}
              tickLine={false}
            />
            <YAxis
              allowDecimals={false}
              tick={axisTick}
              tickFormatter={formatNumber}
              axisLine={false}
              tickLine={false}
              width={48}
            />
            <Tooltip
              contentStyle={tooltipStyle}
              labelStyle={{ color: "var(--foreground)", fontWeight: 700 }}
              formatter={(value) => [
                `${formatNumber(Number(value))} convenios`,
                "Firmas",
              ]}
              labelFormatter={(label) => `Año ${label}`}
            />
            <Bar
              dataKey="total"
              name="Firmas"
              fill={colors.brand}
              radius={[4, 4, 0, 0]}
              maxBarSize={56}
            >
              <LabelList
                dataKey="total"
                position="top"
                fill="var(--foreground)"
              />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartPanel>
  );
}

export function ConveniosPorUnidadChart({
  data,
}: {
  data: EstadisticaPorUnidad[];
}) {
  const chartData = data.map((item) => {
    const name = [item.sigla, item.nombre].filter(Boolean).join(" — ");

    return {
      label:
        name ||
        (item.id_unidad_academica !== null
          ? `Unidad académica ${item.id_unidad_academica}`
          : "Unidad académica sin identificar"),
      total: item.total,
    };
  });
  const chartHeight = Math.max(280, chartData.length * 42 + 72);

  return (
    <ChartPanel
      title="Convenios por unidad académica"
      description="Pasá el cursor sobre una barra para consultar el nombre completo y la cantidad."
      hasData={chartData.length > 0}
    >
      <div
        className="max-h-[560px] overflow-y-auto"
        role="group"
        aria-label="Gráfico de barras horizontales de convenios por unidad académica"
      >
        <ResponsiveContainer width="100%" height={chartHeight} minWidth={0}>
          <BarChart
            data={chartData}
            layout="vertical"
            margin={{ top: 8, right: 32, bottom: 8, left: 0 }}
          >
            <CartesianGrid
              horizontal={false}
              stroke="var(--ui-border)"
              strokeDasharray="3 3"
            />
            <XAxis
              type="number"
              allowDecimals={false}
              tick={axisTick}
              tickFormatter={formatNumber}
              axisLine={{ stroke: "var(--ui-border)" }}
              tickLine={false}
            />
            <YAxis
              type="category"
              dataKey="label"
              width={168}
              interval={0}
              tick={axisTick}
              tickFormatter={(value: string) => shortenLabel(value, 24)}
              axisLine={false}
              tickLine={false}
            />
            <Tooltip
              contentStyle={tooltipStyle}
              labelStyle={{ color: "var(--foreground)", fontWeight: 700 }}
              formatter={(value) => [
                `${formatNumber(Number(value))} convenios`,
                "Cantidad",
              ]}
              labelFormatter={(label) => String(label)}
            />
            <Bar
              dataKey="total"
              name="Convenios"
              fill={colors.brand}
              radius={[0, 4, 4, 0]}
              maxBarSize={24}
            >
              <LabelList
                dataKey="total"
                position="right"
                fill="var(--foreground)"
              />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartPanel>
  );
}
