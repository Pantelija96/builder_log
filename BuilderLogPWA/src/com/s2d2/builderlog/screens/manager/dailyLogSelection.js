import { useState } from 'react';
import Session from '../../network/Session.js';
import DailyLogUtils from '../../dailylogs/DailyLogUtils.js';
import TimeUtils from '../../utils/TimeUtils.js';

export function useDailyLogSelection() {
  const [selection, setSelection] = useState(() => ({
    site: Session.getDefaultConstructionSite(),
    date: TimeUtils.formatDate(new Date()),
    initialLog: Session.getDefaultDailyLog(),
  }));
  const setSite = site => setSelection(current => ({ ...current, site, initialLog: null }));
  const setDate = date => setSelection(current => ({ ...current, date, initialLog: null }));
  return { selection, setSite, setDate };
}

export function loadSelectedDailyLog(site, date, log, callback) {
  if (log && log.getConstructionSiteId() === site.getId() && log.getDailyLogDate() === date) {
    callback.onSuccess(log);
  } else {
    DailyLogUtils.createOrUpdateDailyLog(site, date, callback);
  }
}
