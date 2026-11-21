export type ResumenPanel = {
  firmados_ultimo_anio: number;
  vigentes: number;
  en_tramite: number;
  en_preaviso: number;
  vencidos: number;
  por_estado: Record<string, number>;
};

export type UnidadPanel = {
  id_unidad_academica: number;
  sigla: string | null;
  nombre: string | null;
  total: number;
};

export type MovimientoPanel = {
  id_instrumento: number;
  titulo: string | null;
  estado_anterior: string | null;
  estado_nuevo: string;
  fecha_hora: string | null;
  motivo: string | null;
  usuario: string | null;
};

export type PendienteFirma = {
  id_instrumento: number;
  titulo: string;
  unidad_academica: string | null;
  firmantes: string[];
  aprobado_el: string | null;
  horas_pendiente: number | null;
  demorado: boolean;
};

export type NotificacionPanel = {
  id_notificacion: number;
  tipo: string;
  mensaje: string;
  fecha_generacion: string | null;
  leida: boolean;
  id_instrumento: number | null;
};

export type AlertaPanel = {
  tipo: 'PREAVISO' | 'FIRMA_DEMORADA';
  id_instrumento: number;
  titulo: string;
  mensaje: string;
  dias_restantes?: number;
  horas_pendiente?: number;
};

export type PanelData = {
  resumen: ResumenPanel;
  por_unidad: UnidadPanel[];
  ultimos_movimientos: MovimientoPanel[];
  pendientes_firma: PendienteFirma[];
  notificaciones: { no_leidas: number; items: NotificacionPanel[] };
  alertas: AlertaPanel[];
  generado_el: string;
};
